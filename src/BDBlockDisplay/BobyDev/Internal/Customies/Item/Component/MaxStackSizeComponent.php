<?php
declare(strict_types=1);
namespace BDBlockDisplay\BobyDev\Internal\Customies\Item\Component;
final class MaxStackSizeComponent implements ItemComponent{
    public function __construct(private int $size){}
    public function getName():string{return 'minecraft:max_stack_size';}
    public function getValue():int{return $this->size;}
    public function isProperty():bool{return false;}
}
