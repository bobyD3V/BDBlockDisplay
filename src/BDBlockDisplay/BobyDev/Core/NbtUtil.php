<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Core;

use pocketmine\nbt\tag\CompoundTag;

final class NbtUtil{
    public static function readIntTag(CompoundTag $nbt,string $name,int $default):int{
        $tag=$nbt->getTag($name);
        if($tag===null){return $default;}
        $value=$tag->getValue();
        return is_int($value)?$value:$default;
    }
}
