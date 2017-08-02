<?php

/*
 * This file is part of the Indragunawan\Generator package.
 *
 * (c) Indra Gunawan <hello@indra.my.id>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Indragunawan\Generator;

use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    private const VERSION = '0.1.0';

    public function __construct()
    {
        parent::__construct('PHP Common Generator', self::VERSION);

        $this->add(new Command\GenerateGetSetCommand());
    }

    public function getLongVersion()
    {
        return parent::getLongVersion().' by <comment>Indra Gunawan</comment>';
    }
}
