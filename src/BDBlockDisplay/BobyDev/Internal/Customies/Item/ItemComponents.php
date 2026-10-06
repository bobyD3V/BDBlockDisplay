<?php
declare(strict_types=1);
namespace BDBlockDisplay\BobyDev\Internal\Customies\Item;
use BDBlockDisplay\BobyDev\Internal\Customies\Item\Component\ItemComponent;
interface ItemComponents{
    public function addComponent(ItemComponent $component):void;
    public function hasComponent(string $name):bool;
    public function getComponents():array;
}
