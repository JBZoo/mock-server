<?php

/**
 * JBZoo Toolbox - Mock-Server
 *
 * This file is part of the JBZoo Toolbox project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package    Mock-Server
 * @license    MIT
 * @copyright  Copyright (C) JBZoo.com, All rights reserved.
 * @link       https://github.com/JBZoo/Mock-Server
 */

declare(strict_types=1);

use JBZoo\MockServer\Server\Request;

use function JBZoo\Data\json;

return [
    'request' => [
        'method' => 'GET|DELETE',
        'path'   => '/' . pathinfo(__FILE__, PATHINFO_FILENAME)
    ],

    'response' => [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => (string)json(['result' => true]),
    ],

    'control' => [
        'crazy' => static function (Request $request): bool {
            return $request->getMethod() === 'GET';
        }
    ]
];
