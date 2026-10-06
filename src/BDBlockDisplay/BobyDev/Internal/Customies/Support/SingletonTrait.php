<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Internal\Customies\Support;

/** Small internal singleton helper; avoids depending on external Customies code. */
trait SingletonTrait{
    private static ?self $instance=null;
    final public static function getInstance():self{return self::$instance ??= new self();}
}
