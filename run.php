<?php

require_once './vendor/autoload.php';

use function Termwind\{render};

// single line html...
render('<div class="px-1 bg-green-300">Termwind</div>');

// multi-line html...
render(<<<'HTML'
    <div>
        <div class="px-1 bg-green-600">Termwind</div>
        <em class="ml-1">
          Give your CLI apps a unique look
        </em>
    </div>
HTML);
