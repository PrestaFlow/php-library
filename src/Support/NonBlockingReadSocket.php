<?php

namespace PrestaFlow\Library\Support;

use Wrench\Socket\ClientSocket;

/**
 * Socket Wrench qui lit sans bloquer, pour les navigateurs distants (wss://).
 *
 * Sur une connexion chiffrée, AbstractSocket::receive() voit stream_select()
 * annoncer des données, puis fread() sur la socket bloquante attend jusqu'au
 * délai de socket (5 s par défaut) faute d'un enregistrement TLS complet :
 * contre Cloudflare Browser Run, une commande CDP sur deux ou trois prenait
 * 5 s au lieu de 40 ms. En lecture non bloquante, fread() rend aussitôt ce qui
 * est déjà déchiffré et chrome-php relit au tour suivant.
 *
 * La socket repasse en bloquant après la lecture : l'écriture (send()) et la
 * poignée de main restent celles de Wrench.
 *
 * Passée au client Wrench par l'option `socket_class` (voir RemoteBrowser).
 */
class NonBlockingReadSocket extends ClientSocket
{
    public function receive(int $length = self::DEFAULT_RECEIVE_LENGTH, float $waitSeconds = 0.0): string
    {
        if ($waitSeconds > 0) {
            $this->waitForData($waitSeconds);
        }

        if (!$this->socket) {
            return parent::receive($length, 0.0);
        }

        \stream_set_blocking($this->socket, false);

        try {
            return parent::receive($length, 0.0);
        } finally {
            // parent::receive() a pu se déconnecter (feof) et remettre la socket à null.
            if ($this->socket) {
                \stream_set_blocking($this->socket, true);
            }
        }
    }
}
