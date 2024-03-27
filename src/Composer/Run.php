<?php

namespace PrestaFlow\Library\Composer;


use Composer\Script\Event;
use function Termwind\{render};
use Symfony\Component\Console\Output\OutputInterface;
use Termwind\HtmlRenderer;

class Run
{
    public static function execute(Event $event)
    {
        $args = $event->getArguments();
        if (is_array($args) && count($args)) {
            $className = 'PrestaFlow\\Library\\Tests\\Suites\\' . str_replace('/', '\\', $args[0]);
            //$suite = new $className();
            //$suite->run();
            //var_dump($suite->results(false));
            // single line html...
            $html = '<fg=black;bg=yellow;options=bold> WARN </> Unable to get coverage using Xdebug. Did you set <href=https://xdebug.org/docs/code_coverage#mode>Xdebug\'s coverage mode</>?</>>';
            (new HtmlRenderer)->render($html, OutputInterface::OUTPUT_NORMAL);


        }
    }
}

#composer run-suite Modules/WishlistTest
