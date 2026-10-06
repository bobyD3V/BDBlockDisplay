<?php
declare(strict_types=1);
namespace BDBlockDisplay\BobyDev\Internal\Customies\Item\Component;
final class UseAnimationComponent implements ItemComponent{
    public const ANIMATION_NONE=0;
    public const ANIMATION_EAT=1;
    public const ANIMATION_DRINK=2;
    public function __construct(private int $animation){}
    public function getName():string{return 'use_animation';}
    public function getValue():int{return $this->animation;}
    public function isProperty():bool{return true;}
}
