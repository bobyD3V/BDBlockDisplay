<?php
declare(strict_types=1);
namespace BDBlockDisplay\BobyDev\Internal\Customies\Item;
use BDBlockDisplay\BobyDev\Internal\Customies\Item\Component\IconComponent;
use BDBlockDisplay\BobyDev\Internal\Customies\Item\Component\MaxStackSizeComponent;
use BDBlockDisplay\BobyDev\Internal\Customies\Item\Component\UseAnimationComponent;
trait ItemComponentsTrait{
    /** @var array<string,\BDBlockDisplay\BobyDev\Internal\Customies\Item\Component\ItemComponent> */
    private array $components=[];
    public function addComponent(\BDBlockDisplay\BobyDev\Internal\Customies\Item\Component\ItemComponent $component):void{$this->components[$component->getName()]=$component;}
    public function hasComponent(string $name):bool{return isset($this->components[$name]);}
    public function getComponents():array{return $this->components;}
    protected function initComponent(string $texture):void{
        $this->addComponent(new IconComponent($texture));
        $this->addComponent(new MaxStackSizeComponent($this->getMaxStackSize()));
        $this->addComponent(new UseAnimationComponent(UseAnimationComponent::ANIMATION_NONE));
    }
}
