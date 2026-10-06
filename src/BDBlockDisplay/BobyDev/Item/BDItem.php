<?php
declare(strict_types=1);
namespace BDBlockDisplay\BobyDev\Item;
use BDBlockDisplay\BobyDev\Internal\Customies\Item\ItemComponents;
use BDBlockDisplay\BobyDev\Internal\Customies\Item\ItemComponentsTrait;
use pocketmine\item\Item;
abstract class BDItem extends Item implements ItemComponents{
    use ItemComponentsTrait;
    protected function boot(string $texture):void{$this->initComponent($texture);}
    public function getMaxStackSize():int{return 1;}
}
