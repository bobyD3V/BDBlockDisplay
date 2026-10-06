<?php
declare(strict_types=1);
namespace BDBlockDisplay\BobyDev\Internal\Customies\Util;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use function array_is_list;
final class NBT{
    public static function getTagType(mixed $value):mixed{
        if($value instanceof \pocketmine\nbt\tag\Tag)return clone $value;
        if(is_string($value))return new StringTag($value);
        if(is_int($value))return new IntTag($value);
        if(is_float($value))return new FloatTag($value);
        if(is_bool($value))return new ByteTag($value ? 1 : 0);
        if(is_array($value)){
            if(array_is_list($value)){
                $list=new ListTag();
                foreach($value as $entry){$tag=self::getTagType($entry);if($tag instanceof \pocketmine\nbt\tag\Tag)$list->push($tag);}
                return $list;
            }
            $compound=CompoundTag::create();
            foreach($value as $key=>$entry){$tag=self::getTagType($entry);if($tag instanceof \pocketmine\nbt\tag\Tag)$compound->setTag((string)$key,$tag);}
            return $compound;
        }
        if($value===null)return new StringTag('');
        throw new \InvalidArgumentException('Unsupported component value type: '.get_debug_type($value));
    }
}
