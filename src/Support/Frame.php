<?php

namespace PrestaFlow\Library\Support;

use HeadlessChromium\Communication\Message;
use HeadlessChromium\Communication\Session;
use PrestaFlow\Library\Exceptions\TimeoutException;
use Throwable;

/**
 * Une iframe d'un autre domaine : champs carte Stripe, PayPal, Adyen, Mollie…
 *
 * Chrome isole ces iframes dans un processus à part (site isolation) : la page ne peut ni lire
 * leur DOM ni y cliquer par sélecteur, et un clic à la position de l'iframe est fragile. On
 * s'attache donc à la cible CDP de l'iframe (Target.attachToTarget, flatten), trouvée par son
 * URL, et on agit dedans comme sur une page :
 *
 *   $card = $checkoutPage->frame('js.stripe.com');
 *   $card->click('#cardNumber');           // vrai clic souris, dans l'iframe
 *   $card->type('#cardNumber', '4242424242424242');
 *   Expect::that($card->value('#cardNumber'))->isNotEmpty();
 *
 * Les frappes partent en Input.dispatchKeyEvent vers la session de l'iframe : keydown, keypress
 * et input arrivent comme au clavier, ce que les champs de paiement exigent (ils ignorent une
 * valeur posée en JS).
 */
class Frame
{
    public function __construct(
        private Session $session,
        private string $url,
        private int $timeoutMs = 5000,
    ) {}

    /**
     * L'iframe dont l'URL contient $urlContains, attendue jusqu'à $timeoutMs ; null sinon.
     * Plusieurs candidates : la dernière créée (Chrome liste les cibles de la plus récente à la
     * plus ancienne, un formulaire rechargé remplace l'ancienne iframe).
     *
     * @param  object  $page  HeadlessChromium\Page
     */
    public static function find(object $page, string $urlContains, int $timeoutMs = 10000): ?self
    {
        $connection = $page->getSession()->getConnection();
        $deadline = microtime(true) + $timeoutMs / 1000;
        do {
            try {
                $targets = (array) $connection->sendMessageSync(new Message('Target.getTargets'))->getResultData('targetInfos');
                foreach ($targets as $target) {
                    if (($target['type'] ?? null) === 'iframe' && str_contains((string) ($target['url'] ?? ''), $urlContains)) {
                        return new self($connection->createSession($target['targetId']), (string) $target['url']);
                    }
                }
            } catch (Throwable) {
                // Navigation en cours : on réessaie jusqu'au délai.
            }
            usleep(200000);
        } while (microtime(true) < $deadline);

        return null;
    }

    /**
     * URL des iframes ouvertes dans le navigateur, pour trouver le motif à passer à find().
     *
     * @param  object  $page  HeadlessChromium\Page
     * @return list<string>
     */
    public static function urls(object $page): array
    {
        try {
            $targets = (array) $page->getSession()->getConnection()
                ->sendMessageSync(new Message('Target.getTargets'))->getResultData('targetInfos');
        } catch (Throwable) {
            return [];
        }

        return array_values(array_map(
            fn (array $t) => (string) $t['url'],
            array_filter($targets, fn ($t) => ($t['type'] ?? null) === 'iframe' && ! str_starts_with((string) ($t['url'] ?? ''), 'chrome'))
        ));
    }

    public function url(): string
    {
        return $this->url;
    }

    /** Évalue une expression JS dans l'iframe et renvoie sa valeur (promesses attendues). */
    public function evaluate(string $expression): mixed
    {
        $response = $this->session->sendMessageSync(new Message('Runtime.evaluate', [
            'expression' => $expression,
            'returnByValue' => true,
            'awaitPromise' => true,
        ]), $this->timeoutMs);
        $result = (array) $response->getResultData('result');
        $exception = $response->getResultData('exceptionDetails');
        if ($exception !== null) {
            throw new \RuntimeException('Erreur JS dans l\'iframe '.$this->url.' : '.(string) (($exception['exception']['description'] ?? null) ?: ($exception['text'] ?? '')));
        }

        return $result['value'] ?? null;
    }

    /** Attend qu'une expression JS soit vraie dans l'iframe. */
    public function waitFor(string $expression, int $timeoutMs = 10000): bool
    {
        $deadline = microtime(true) + $timeoutMs / 1000;
        do {
            try {
                if ($this->evaluate('!!('.$expression.')') === true) {
                    return true;
                }
            } catch (Throwable) {
                // Iframe en plein rechargement : on réessaie.
            }
            usleep(200000);
        } while (microtime(true) < $deadline);

        return false;
    }

    /** L'élément est présent et affiché dans l'iframe (attendu jusqu'à $timeoutMs). */
    public function has(string $selector, int $timeoutMs = 5000): bool
    {
        return $this->waitFor('(function(){var e=document.querySelector('.self::js($selector).');'
            .'if(!e)return false;var r=e.getBoundingClientRect();return r.width>0&&r.height>0;})()', $timeoutMs);
    }

    /**
     * Vrai clic souris au centre de l'élément (événements de confiance, comme un utilisateur),
     * après l'avoir fait défiler dans la vue de l'iframe.
     */
    public function click(string $selector, int $timeoutMs = 5000): void
    {
        $this->requireElement($selector, $timeoutMs);
        $point = (array) $this->evaluate('(function(){var e=document.querySelector('.self::js($selector).');'
            .'e.scrollIntoView({block:"center",inline:"center"});var r=e.getBoundingClientRect();'
            .'return {x:r.left+r.width/2,y:r.top+r.height/2};})()');
        foreach (['mouseMoved', 'mousePressed', 'mouseReleased'] as $type) {
            $this->session->sendMessageSync(new Message('Input.dispatchMouseEvent', [
                'type' => $type,
                'x' => (float) $point['x'],
                'y' => (float) $point['y'],
                'button' => $type === 'mouseMoved' ? 'none' : 'left',
                'clickCount' => $type === 'mouseMoved' ? 0 : 1,
            ]), $this->timeoutMs);
        }
    }

    /**
     * Vrai clic sur l'élément cliquable dont le texte contient $text (insensible à la casse) :
     * le plus petit qui convienne, pour ne pas viser un conteneur dont le centre tombe sur un
     * autre élément. Pratique quand les éléments n'ont ni id ni attribut stable, comme les moyens
     * de paiement de Stripe (« Carte bancaire », « Bancontact »…).
     */
    public function clickText(string $text, string $among = '[role=button],[role=radio],[role=tab],button,a,label', int $timeoutMs = 5000): void
    {
        $find = '(function(){var t='.self::js(mb_strtolower($text)).';'
            .'var c=[].slice.call(document.querySelectorAll('.self::js($among).')).filter(function(e){'
            .'var r=e.getBoundingClientRect();return r.width>0&&r.height>0&&(e.textContent||"").toLowerCase().indexOf(t)!==-1;});'
            .'if(!c.length)return false;c.sort(function(a,b){var x=a.getBoundingClientRect(),y=b.getBoundingClientRect();return x.width*x.height-y.width*y.height;});'
            .'[].forEach.call(document.querySelectorAll("[data-pf-click]"),function(e){e.removeAttribute("data-pf-click");});'
            .'c[0].setAttribute("data-pf-click","1");return true;})()';
        if (! $this->waitFor($find, $timeoutMs)) {
            throw new TimeoutException(sprintf('Aucun élément « %s » dans l\'iframe %s', $text, $this->url));
        }
        $this->click('[data-pf-click="1"]');
    }

    /**
     * Donne le focus à l'élément puis tape $text touche par touche. Le champ n'est pas vidé
     * avant : passer $clear = true pour effacer sa valeur d'abord (Ctrl/Cmd+A puis Retour arrière).
     */
    public function type(string $selector, string $text, bool $clear = false, int $delayMs = 20): void
    {
        $this->requireElement($selector, 5000);
        $this->evaluate('(function(){var e=document.querySelector('.self::js($selector).');e.scrollIntoView({block:"center"});e.focus();})()');
        if ($clear) {
            $this->evaluate('(function(){var e=document.activeElement;if(e&&"select" in e){e.select();}})()');
            $this->key('Backspace', 8);
        }
        foreach (mb_str_split($text) as $char) {
            $this->session->sendMessageSync(new Message('Input.dispatchKeyEvent', ['type' => 'keyDown', 'key' => $char, 'text' => $char, 'unmodifiedText' => $char]), $this->timeoutMs);
            $this->session->sendMessageSync(new Message('Input.dispatchKeyEvent', ['type' => 'keyUp', 'key' => $char]), $this->timeoutMs);
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }
    }

    /** Une touche spéciale : Tab, Enter, Backspace… */
    public function key(string $key, int $keyCode = 0): void
    {
        foreach (['rawKeyDown', 'keyUp'] as $type) {
            $this->session->sendMessageSync(new Message('Input.dispatchKeyEvent', array_filter([
                'type' => $type,
                'key' => $key,
                'code' => $key,
                'windowsVirtualKeyCode' => $keyCode ?: null,
            ])), $this->timeoutMs);
        }
    }

    /** Valeur d'un champ de l'iframe, null s'il est absent. */
    public function value(string $selector): ?string
    {
        $value = $this->evaluate('(function(){var e=document.querySelector('.self::js($selector).');return e?String(e.value):null;})()');

        return $value === null ? null : (string) $value;
    }

    private function requireElement(string $selector, int $timeoutMs): void
    {
        if (! $this->has($selector, $timeoutMs)) {
            throw new TimeoutException(sprintf('Élément « %s » introuvable dans l\'iframe %s', $selector, $this->url));
        }
    }

    private static function js(string $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
