<?php
declare(strict_types=1);
namespace BDBlockDisplay\BobyDev\Internal\Customies\Item\Component;
final class IconComponent implements ItemComponent{
    public function __construct(private string $defaultTexture, private ?string $dyedTexture=null, private ?string $trimTexture=null){
        $this->dyedTexture ??= $defaultTexture;
        $this->trimTexture ??= $defaultTexture;
    }
    public function getName():string{return 'minecraft:icon';}
    public function getValue():array{return ['texture'=>$this->defaultTexture,'textures'=>['default'=>$this->defaultTexture,'dyed'=>$this->dyedTexture,'icon_trim'=>$this->trimTexture]];}
    public function isProperty():bool{return true;}
}
