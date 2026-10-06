<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\UI;

use BDBlockDisplay\BobyDev\Core\BlockRegistry;
use BDBlockDisplay\BobyDev\Display\BlockDisplayManager;
use BDBlockDisplay\BobyDev\Entity\DisplayEntity;
use BDBlockDisplay\BobyDev\Entity\HandleEntity;
use BDBlockDisplay\BobyDev\Form\SimpleForm;
use pocketmine\entity\Location;
use pocketmine\player\Player;

final class EditorUI{
    public function __construct(private BlockDisplayManager $manager){}

        private function openMenu(Player $p,DisplayEntity $d):void{ $this->openDisplayMenu($p,$d); }

    public function iconButton(string $text,?string $path=null):array{
            $b=['text'=>$text];
            if($path!==null)$b['image']=['type'=>'path','data'=>$path];
            return $b;
        }

        public function openDisplayMenu(Player $p,DisplayEntity $d):void{
            $control=$this->manager->hasPlayerControl($p)&&$this->manager->getPlayerControlDisplay($p)?->getId()===$d->getId();
            $mode=BlockDisplayManager::MODE_NAMES[$d->getMode()]??'Move';
            $status=$control?'§aPLAYER CONTROL: ACTIVE':'§7PLAYER CONTROL: OFF';
            $buttons=[
                $this->iconButton("§fPLAYER CONTROL\n§7Walk to move the block",'textures/ui/bd/player_control'),
                $this->iconButton("§fROTATION / LEAN\n§7Rotate X/Y • Lean Z",'textures/ui/bd/rotate'),
                $this->iconButton("§fSCALE\n§7Resize display block",'textures/ui/bd/scale'),
                $this->iconButton("§fEXACT VALUES\n§7Position • Rotation • Size",'textures/ui/bd/exact'),
                $this->iconButton("§fSET BLOCK\n§7Change displayed block",'textures/ui/bd/setblock'),
                $this->iconButton("§fQUICK MOVE\n§7Move along X • Y • Z",'textures/ui/bd/right'),
                $this->iconButton("§fNEXT VARIANT\n§7Cycle block variant",'textures/ui/bd/variant'),
                $this->iconButton("§fDUPLICATE\n§7Create a copy",'textures/ui/bd/duplicate'),
                $this->iconButton("§fGROUP CONTROL\n§7Select and edit a group",'textures/ui/bd/player_control'),
                $this->iconButton("§fWALKABLE / SOLID\n§7Toggle virtual collision",'textures/ui/bd/walkable'),
                $this->iconButton("§fREFRESH HANDLES\n§7Rebuild editor handles",'textures/ui/bd/refresh'),
                $this->iconButton($control?"§cSTOP PLAYER CONTROL\n§7End continuous movement":"§fSTOP DRAG\n§7End active drag",'textures/ui/bd/stop'),
                $this->iconButton("§cDELETE DISPLAY\n§7Remove this display",'textures/ui/bd/delete'),
                $this->iconButton("§7CLOSE\n§8Close editor",'textures/ui/bd/close'),
            ];
            $content="§7Block: §f{$d->getBlockId()}\n".
                '§7Size: §f'.round($d->getDisplaySize(),4)."\n".
                '§7Rotation: §f'.round($d->getLocation()->pitch,3).', '.round($d->getLocation()->yaw,3).', '.round($d->getRoll(),3)."\n".
                '§7Mode: §f'.$mode."\n".$status."\n§8Player control follows your movement 1:1.";
            $json=json_encode(['type'=>'form','title'=>'§lBD BLOCK DISPLAY','content'=>$content,'buttons'=>$buttons], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $form=new SimpleForm($json,function(Player $p,$data)use($d){
                if(!is_int($data))return;
                switch($data){
                    case 0:
                        if($this->manager->hasPlayerControl($p)){ $this->manager->stopPlayerControl($p,true); $this->openMenu($p,$d); }
                        else { $this->manager->startPlayerControl($p,$d); }
                        return;
                    case 1:$this->openRotationLeanForm($p,$d);return;
                    case 2:$this->openScaleForm($p,$d);return;
                    case 3:$this->exactForm($p,$d);return;
                    case 4:$this->spawnBlockForm($p,$d);return;
                    case 5:$this->openQuickMoveForm($p,$d);return;
                    case 6:$d->setVariant($d->getVariant()+1);$p->sendMessage('§aVariant changed to '.$d->getVariant());break;
                    case 7:$this->manager->duplicate($p,$d);break;
                    case 8:$this->openGroupMenu($p);return;
                    case 9:$d->setSolid(!$d->isSolid());$p->sendMessage('§eWalkable / Solid: '.($d->isSolid()?'ON':'OFF').' §7(virtual collision)');break;
                    case 10:$this->manager->refreshHandles($p);break;
                    case 11:$this->manager->stopPlayerControl($p,true);$this->manager->stopDrag($p);break;
                    case 12:$this->manager->deleteDisplay($d);$this->manager->clearSelection($p);return;
                    case 13:return;
                    default:return;
                }
                $this->manager->syncModel($d);$this->manager->updateHandlePositions($p);$this->openMenu($p,$d);
            });
            $p->sendForm($form);
        }


        private function openQuickMoveForm(Player $p,DisplayEntity $d):void{
            $f=new SimpleForm(json_encode(['type'=>'form','title'=>'§lQUICK MOVE','content'=>"§7One tap = ".BlockDisplayManager::MOVE_STEP." block.\n§8For continuous movement use PLAYER CONTROL.",'buttons'=>[
                $this->iconButton('§fMOVE X +','textures/ui/bd/right'),$this->iconButton('§fMOVE X -','textures/ui/bd/left'),
                $this->iconButton('§fMOVE Y +','textures/ui/bd/up'),$this->iconButton('§fMOVE Y -','textures/ui/bd/down'),
                $this->iconButton('§fMOVE Z +','textures/ui/bd/right'),$this->iconButton('§fMOVE Z -','textures/ui/bd/left'),
                $this->iconButton('§7BACK','textures/ui/bd/left')
            ]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),function(Player $p,$data)use($d){
                if(!is_int($data))return;
                if($data===0)$this->manager->nudge($d,0,BlockDisplayManager::MOVE_STEP);
                elseif($data===1)$this->manager->nudge($d,0,-BlockDisplayManager::MOVE_STEP);
                elseif($data===2)$this->manager->nudge($d,1,BlockDisplayManager::MOVE_STEP);
                elseif($data===3)$this->manager->nudge($d,1,-BlockDisplayManager::MOVE_STEP);
                elseif($data===4)$this->manager->nudge($d,2,BlockDisplayManager::MOVE_STEP);
                elseif($data===5)$this->manager->nudge($d,2,-BlockDisplayManager::MOVE_STEP);
                else { $this->openMenu($p,$d); return; }
                $this->manager->syncModel($d);$this->manager->updateHandlePositions($p);$this->openQuickMoveForm($p,$d);
            });
            $p->sendForm($f);
        }


        private function openRotationLeanForm(Player $p,DisplayEntity $d):void{
            $buttons=[
                $this->iconButton('§fX -5°','textures/ui/bd/minus'),$this->iconButton('§fX +5°','textures/ui/bd/plus'),
                $this->iconButton('§fY -5°','textures/ui/bd/minus'),$this->iconButton('§fY +5°','textures/ui/bd/plus'),
                $this->iconButton('§fSIDE LEAN -5°','textures/ui/bd/minus'),$this->iconButton('§fSIDE LEAN +5°','textures/ui/bd/plus'),
                $this->iconButton('§fLEAN -10°','textures/ui/bd/minus'),$this->iconButton('§fLEAN +10°','textures/ui/bd/plus'),
                $this->iconButton('§fLEAN -15°','textures/ui/bd/minus'),$this->iconButton('§fLEAN +15°','textures/ui/bd/plus'),
                $this->iconButton('§fLEAN -30°','textures/ui/bd/minus'),$this->iconButton('§fLEAN +30°','textures/ui/bd/plus'),
                $this->iconButton('§fLEAN -45°','textures/ui/bd/minus'),$this->iconButton('§fLEAN +45°','textures/ui/bd/plus'),
                $this->iconButton('§7RESET ROTATION','textures/ui/bd/refresh'),
                $this->iconButton('§7BACK','textures/ui/bd/left')
            ];
            $f=new SimpleForm(json_encode(['type'=>'form','title'=>'§lROTATION / LEAN','content'=>"§7X/Y rotate the block. Z is the side-lean axis.\n§fCurrent Z lean: ".round($d->getRoll(),2)."°",'buttons'=>$buttons],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),function(Player $p,$data)use($d){
                if(!is_int($data))return;
                $map=[0=>[0,-5],1=>[0,5],2=>[1,-5],3=>[1,5],4=>[2,-5],5=>[2,5],6=>[2,-10],7=>[2,10],8=>[2,-15],9=>[2,15],10=>[2,-30],11=>[2,30],12=>[2,-45],13=>[2,45]];
                if(isset($map[$data])){[$axis,$delta]=$map[$data];$this->manager->rotateBy($d,$axis,$delta);$this->manager->syncModel($d);$this->openRotationLeanForm($p,$d);return;}
                if($data===14){$l=$d->getLocation();$d->teleport($d->getPosition(),0,0);$d->setRoll(0);$this->manager->syncModel($d);$this->openRotationLeanForm($p,$d);return;}
                $this->openMenu($p,$d);
            });
            $p->sendForm($f);
        }


        private function openScaleForm(Player $p,DisplayEntity $d):void{
            $buttons=[];foreach([[-50,'§f-50%'],[-25,'§f-25%'],[-10,'§f-10%'],[5,'§f+5%'],[10,'§f+10%'],[25,'§f+25%'],[50,'§f+50%']] as [$pct,$label])$buttons[]=$this->iconButton($label,$pct<0?'textures/ui/bd/minus':'textures/ui/bd/plus');
            $buttons[]=$this->iconButton('§7BACK','textures/ui/bd/left');
            $f=new SimpleForm(json_encode(['type'=>'form','title'=>'§lSCALE','content'=>"§7Current size: §f".round($d->getDisplaySize(),4)."§r\n§8Percent changes are relative to current size.",'buttons'=>$buttons]),function(Player $p,$data)use($d){if(!is_int($data))return;$vals=[-50,-25,-10,5,10,25,50];if(isset($vals[$data])){$factor=1+$vals[$data]/100;$d->setDisplaySize($d->getDisplaySize()*$factor);$this->manager->syncModel($d);$this->manager->refreshHandles($p);$this->openScaleForm($p,$d);}});$p->sendForm($f);
        }


        private function openGroupMenu(Player $p):void{
            $count=$this->manager->groupCount($p);
            $buttons=[
                $this->iconButton("§fSELECT ALL NEARBY\n§7Radius: 16 blocks",'textures/ui/bd/player_control'),
                $this->iconButton('§fCLEAR GROUP','textures/ui/bd/delete'),
                $this->iconButton('§fMOVE X +0.5','textures/ui/bd/right'),
                $this->iconButton('§fMOVE X -0.5','textures/ui/bd/left'),
                $this->iconButton('§fMOVE Y +0.5','textures/ui/bd/up'),
                $this->iconButton('§fMOVE Y -0.5','textures/ui/bd/down'),
                $this->iconButton('§fMOVE Z +0.5','textures/ui/bd/right'),
                $this->iconButton('§fMOVE Z -0.5','textures/ui/bd/left'),
                $this->iconButton('§fROTATE Y +15°','textures/ui/bd/plus'),
                $this->iconButton('§fROTATE Y -15°','textures/ui/bd/minus'),
                $this->iconButton('§fSCALE +10%','textures/ui/bd/plus'),
                $this->iconButton('§fSCALE -10%','textures/ui/bd/minus'),
                $this->iconButton('§fDUPLICATE GROUP','textures/ui/bd/duplicate'),
                $this->iconButton('§cDELETE GROUP','textures/ui/bd/delete'),
                $this->iconButton('§7BACK','textures/ui/bd/left'),
            ];
            $f=new SimpleForm(json_encode(['type'=>'form','title'=>'§lGROUP CONTROL','content'=>"§7Selected members: §f".$count."\n§8Use the Group Tool to tap displays and add or remove them.",'buttons'=>$buttons],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),function(Player $p,$data){
                if(!is_int($data))return;
                switch($data){
                    case 0:$this->manager->selectAllNearbyGroup($p,16);break;
                    case 1:$this->manager->clearGroup($p);break;
                    case 2:$this->manager->moveGroup($p,.5,0,0);break;
                    case 3:$this->manager->moveGroup($p,-.5,0,0);break;
                    case 4:$this->manager->moveGroup($p,0,.5,0);break;
                    case 5:$this->manager->moveGroup($p,0,-.5,0);break;
                    case 6:$this->manager->moveGroup($p,0,0,.5);break;
                    case 7:$this->manager->moveGroup($p,0,0,-.5);break;
                    case 8:$this->manager->rotateGroup($p,1,15);break;
                    case 9:$this->manager->rotateGroup($p,1,-15);break;
                    case 10:$this->manager->scaleGroup($p,1.10);break;
                    case 11:$this->manager->scaleGroup($p,.90);break;
                    case 12:$this->manager->duplicateGroup($p);break;
                    case 13:$this->manager->deleteGroup($p);return;
                    default:$back=$this->manager->selected($p) ?? ($this->manager->groupDisplays($p)[0] ?? null);if($back instanceof DisplayEntity)$this->openMenu($p,$back);return;
                }
                $ds=$this->manager->groupDisplays($p);foreach($ds as $d){$this->manager->syncModel($d);} $this->openGroupMenu($p);
            });
            $p->sendForm($f);
        }


        private function spawnBlockForm(Player $p,DisplayEntity $d):void{$opts=array_keys(BlockRegistry::BLOCKS);sort($opts);$content=[['type'=>'dropdown','text'=>'Block ID','options'=>$opts,'default'=>array_search($d->getBlockId(),$opts,true)?:0],['type'=>'input','text'=>'Or type block ID','default'=>$d->getBlockId()]];$f=new SimpleForm(json_encode(['type'=>'custom_form','title'=>'Set Block','content'=>$content]),function(Player $p,$data)use($d,$opts){if(!is_array($data))return;$id=trim((string)($data[1]??''));if($id===''&&isset($data[0]))$id=$opts[(int)$data[0]]??'';if($d->setBlockId($id)){$this->manager->syncModel($d);$p->sendMessage('§aBlock set to '.$d->getBlockId());}else$p->sendMessage('§cUnknown block ID.');});$p->sendForm($f);}
        private function exactForm(Player $p,DisplayEntity $d):void{$l=$d->getLocation();$c=[['type'=>'input','text'=>'X','default'=>(string)$l->x],['type'=>'input','text'=>'Y','default'=>(string)$l->y],['type'=>'input','text'=>'Z','default'=>(string)$l->z],['type'=>'input','text'=>'Pitch X','default'=>(string)$l->pitch],['type'=>'input','text'=>'Yaw Y','default'=>(string)$l->yaw],['type'=>'input','text'=>'Roll Z','default'=>(string)$d->getRoll()],['type'=>'input','text'=>'Size','default'=>(string)$d->getDisplaySize()]];$f=new SimpleForm(json_encode(['type'=>'custom_form','title'=>'Exact Values','content'=>$c]),function(Player $p,$data)use($d){if(!is_array($data))return;try{$x=(float)$data[0];$y=(float)$data[1];$z=(float)$data[2];$rx=(float)$data[3];$ry=(float)$data[4];$rz=(float)$data[5];$s=(float)$data[6];$d->teleport(new Location($x,$y,$z,$p->getWorld(),$ry,$rx));$d->setRoll($rz);$d->setDisplaySize($s);$this->manager->syncModel($d);$this->manager->refreshHandles($p);$p->sendMessage('§aExact values updated.');}catch(\Throwable $e){$p->sendMessage('§cInvalid values.');}});$p->sendForm($f);}
}
