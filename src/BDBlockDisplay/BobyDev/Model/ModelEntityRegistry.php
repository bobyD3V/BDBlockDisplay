<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Model;

final class ModelEntityRegistry{
    /** @var array<string,string> */ private static array $map=[];
    public static function add(int $s,int $v,string $face,string $class):void{self::$map["$s:$v:$face"]=$class;}
    public static function classFor(int $s,int $v,string $face):?string{return self::$map["$s:$v:$face"]??null;}
}
