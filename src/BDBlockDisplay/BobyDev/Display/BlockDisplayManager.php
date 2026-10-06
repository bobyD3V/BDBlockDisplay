<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Display;

use BDBlockDisplay\BobyDev\main;
use BDBlockDisplay\BobyDev\Core\BlockRegistry;
use BDBlockDisplay\BobyDev\Core\DisplayMath;
use BDBlockDisplay\BobyDev\Entity\DisplayEntity;
use BDBlockDisplay\BobyDev\Entity\HandleEntity;
use BDBlockDisplay\BobyDev\Entity\ModelFaceEntity;
use BDBlockDisplay\BobyDev\Model\ModelEntityRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\player\Player;
use pocketmine\world\World;

final class BlockDisplayManager{
    public const MODE_NAMES=['Off','Move','Rotate','Scale','Calibrate'];
    public const MOVE_STEP=1/16; public const ROT_STEP=15.0; public const SIZE_STEP=1/8;
    /** @var array<int,int[]> */ private array $handles=[];
    /** @var array<string,int> */ private array $selected=[];
    /** @var array<int,array<string,mixed>> */ private array $drags=[];
    /** @var array<int,array{displayId:int,last:Vector3}> */ private array $playerControls=[];
    /** @var array<int,array<int,bool>> */ private array $groupMembers=[];
    /** @var array<int,array<string,array{0:int,1:int,2:int}>> */ private array $clientSolidCells=[];
    /** @var array<int,int> Player -> Altay World::getId() for the client collision snapshot. */ private array $clientSolidWorld=[];
    /** @var array<int,string> Player -> current client collision anchor chunk. */ private array $clientSolidChunk=[];
    private const CLIENT_COLLISION_RADIUS=64.0;
    private const MAX_CLIENT_SOLID_CELLS=4096;
    public function __construct(private main $plugin){}

    public function select(Player $p,DisplayEntity $d):void{
        $oldId=$this->selected[$p->getName()]??null;
        if($oldId!==null && $oldId!==$d->getId()){
            $this->stopPlayerControl($p,false); $this->stopDrag($p); $this->removeHandles($p);
            foreach($p->getWorld()->getEntities() as $e){if($e instanceof DisplayEntity && $e->getId()===$oldId){$e->setInvisible(true);break;}}
        }
        $this->selected[$p->getName()]=$d->getId();$d->setInvisible(false);$this->setMode($d,1);$this->refreshHandles($p);$p->sendMessage('§aSelected BlockDisplay #'.$d->getId().' §7('.$d->getBlockId().')');$d->spawnTo($p);
    }
    public function selected(Player $p):?DisplayEntity{
        $id=$this->selected[$p->getName()]??null;if($id===null)return null;
        foreach($p->getWorld()->getEntities() as $e)if($e instanceof DisplayEntity && $e->getId()===$id && !$e->isClosed())return $e;
        return null;
    }
    public function clearSelection(Player $p):void{
        $this->stopPlayerControl($p,false);$this->stopDrag($p);$this->removeHandles($p);unset($this->groupMembers[$p->getId()]);$id=$this->selected[$p->getName()]??null;unset($this->selected[$p->getName()]);
        if($id!==null && !in_array($id,$this->selected,true)){foreach($p->getWorld()->getEntities() as $e)if($e instanceof DisplayEntity && $e->getId()===$id){$e->setInvisible(true);break;}}
    }
    public function toggleGroupMember(Player $p, DisplayEntity $d): void{
        $pid=$p->getId();
        $this->groupMembers[$pid] ??= [];
        if(isset($this->groupMembers[$pid][$d->getId()])){
            unset($this->groupMembers[$pid][$d->getId()]);
            $p->sendMessage('§7Removed §f#'.$d->getId().' §7from group. §8('.$this->groupCount($p).')');
        }else{
            $this->groupMembers[$pid][$d->getId()]=true;
            $this->selected[$p->getName()]=$d->getId();
            $d->setInvisible(false);
            $p->sendMessage('§aAdded §f#'.$d->getId().' §7'.$d->getBlockId().' to group. §8('.$this->groupCount($p).')');
        }
        $this->groupPrune($p);
    }
    public function clearGroup(Player $p): void{
        unset($this->groupMembers[$p->getId()]);
        $p->sendMessage('§7Group cleared.');
    }
    public function groupCount(Player $p): int{
        $this->groupPrune($p);
        return count($this->groupMembers[$p->getId()] ?? []);
    }
    /** @return DisplayEntity[] */
    public function groupDisplays(Player $p): array{
        $this->groupPrune($p);
        $out=[];
        foreach(array_keys($this->groupMembers[$p->getId()] ?? []) as $id){
            $d=$this->displayById($p,(int)$id);
            if($d!==null)$out[]=$d;
        }
        return $out;
    }
    private function groupPrune(Player $p): void{
        $pid=$p->getId();
        if(!isset($this->groupMembers[$pid]))return;
        foreach(array_keys($this->groupMembers[$pid]) as $id){
            if($this->displayById($p,(int)$id)===null)unset($this->groupMembers[$pid][$id]);
        }
        if($this->groupMembers[$pid]===[])unset($this->groupMembers[$pid]);
    }
    public function selectAllNearbyGroup(Player $p,float $radius=16.0): int{
        $pid=$p->getId();$this->groupMembers[$pid]=[];$pp=$p->getPosition();$r2=$radius*$radius;
        foreach($p->getWorld()->getEntities() as $e){
            if(!$e instanceof DisplayEntity || $e->isClosed())continue;
            if($e->getPosition()->distanceSquared($pp)<=$r2){
                $this->groupMembers[$pid][$e->getId()]=true;$e->setInvisible(false);
            }
        }
        $n=$this->groupCount($p);
        $p->sendMessage('§aSelected §f'.$n.' §adisplays in a '.$radius.' block radius.');
        return $n;
    }
    private function groupCenter(Player $p): ?Vector3{
        $ds=$this->groupDisplays($p);if($ds===[])return null;
        $minX=$minY=$minZ=INF;$maxX=$maxY=$maxZ=-INF;
        foreach($ds as $d){$l=$d->getLocation();$s=$d->getDisplaySize();$minX=min($minX,$l->x-$s/2);$maxX=max($maxX,$l->x+$s/2);$minY=min($minY,$l->y);$maxY=max($maxY,$l->y+$s);$minZ=min($minZ,$l->z-$s/2);$maxZ=max($maxZ,$l->z+$s/2);}
        return new Vector3(($minX+$maxX)/2,($minY+$maxY)/2,($minZ+$maxZ)/2);
    }
    public function moveGroup(Player $p,float $dx,float $dy,float $dz): void{
        foreach($this->groupDisplays($p) as $d){$l=$d->getLocation();$d->teleport(new Location($l->x+$dx,$l->y+$dy,$l->z+$dz,$d->getWorld(),$l->yaw,$l->pitch));$this->syncModel($d);}
    }
    public function rotateGroup(Player $p,int $axis,float $delta): void{
        $center=$this->groupCenter($p);if($center===null)return;$rad=deg2rad($delta);$c=cos($rad);$s=sin($rad);
        foreach($this->groupDisplays($p) as $d){
            $l=$d->getLocation();$v=new Vector3($l->x-$center->x,$l->y-$center->y,$l->z-$center->z);
            if($axis===0){$ny=$v->y*$c-$v->z*$s;$nz=$v->y*$s+$v->z*$c;$v=new Vector3($v->x,$ny,$nz);}
            elseif($axis===1){$nx=$v->x*$c+$v->z*$s;$nz=-$v->x*$s+$v->z*$c;$v=new Vector3($nx,$v->y,$nz);}
            else{$nx=$v->x*$c-$v->y*$s;$ny=$v->x*$s+$v->y*$c;$v=new Vector3($nx,$ny,$v->z);}
            $d->teleport(new Location($center->x+$v->x,$center->y+$v->y,$center->z+$v->z,$d->getWorld(),$l->yaw,$l->pitch));
            $this->rotateAxis($d,$axis,$delta);$this->syncModel($d);
        }
    }
    public function scaleGroup(Player $p,float $factor): void{
        $center=$this->groupCenter($p);if($center===null)return;
        foreach($this->groupDisplays($p) as $d){
            $l=$d->getLocation();$v=new Vector3(($l->x-$center->x)*$factor,($l->y-$center->y)*$factor,($l->z-$center->z)*$factor);
            $d->teleport(new Location($center->x+$v->x,$center->y+$v->y,$center->z+$v->z,$d->getWorld(),$l->yaw,$l->pitch));$d->setDisplaySize($d->getDisplaySize()*$factor);$this->syncModel($d);
        }
    }
    public function duplicateGroup(Player $p): int{
        $ds=$this->groupDisplays($p);if($ds===[]){$p->sendMessage('§cGroup is empty.');return 0;}
        $offset=0.0;foreach($ds as $d)$offset=max($offset,$d->getDisplaySize());$offset=max(1.0,$offset+1.0);
        $new=[];
        foreach($ds as $d){
            $l=$d->getLocation();$n=new DisplayEntity(new Location($l->x+$offset,$l->y,$l->z,$d->getWorld(),$l->yaw,$l->pitch),null);
            $n->setBlockId($d->getBlockId());$n->setDisplaySize($d->getDisplaySize());$n->setRoll($d->getRoll());$n->setSolid($d->isSolid());$n->setVariant($d->getVariant());$n->spawnToAll();$this->syncModel($n);$new[]=$n->getId();
        }
        $this->groupMembers[$p->getId()]=array_fill_keys($new,true);
        $p->sendMessage('§aDuplicated group: §f'.count($new).' §adisplays.');return count($new);
    }

    public function deleteGroup(Player $p): int{
        $n=0;foreach($this->groupDisplays($p) as $d){$this->deleteDisplay($d);$n++;}$this->clearGroup($p);return $n;
    }

    public function setMode(DisplayEntity $d,int $mode):void{
        $d->setMode($mode);$d->syncModelMetadata();
        foreach($this->drags as $pid=>$drag){if(($drag['displayId']??-1)===$d->getId())unset($this->drags[$pid]);}
        foreach($this->playerControls as $pid=>$control){if(($control['displayId']??-1)===$d->getId())unset($this->playerControls[$pid]);}
    }
    public function findInSight(Player $p,array $classes,int $max=48):?Entity{
        $o=$p->getEyePos();$dir=$p->getDirectionVector();$best=null;$bestT=$max+1;
        foreach($p->getWorld()->getEntities() as $e){
            if($e->isClosed()||$e->getId()===$p->getId())continue;$ok=false;foreach($classes as $c)if($e instanceof $c){$ok=true;break;}if(!$ok)continue;
            $v=$e->getPosition()->subtractVector($o);$t=$v->dot($dir);if($t<0||$t>$max||$t>=$bestT)continue;$closest=$o->addVector($dir->multiply($t));$r=$closest->distance($e->getPosition());
            $allow=max(.65,$e->getSize()->getWidth()*$e->getScale()/2+.35);if($r<=$allow){$best=$e;$bestT=$t;}
        }
        return $best;
    }
    private function handleDefinitions(DisplayEntity $d):array{
        $s=max(.25,$d->getDisplaySize());$reach=max(.72,$s*.5+.38);$c=$this->editorCenter($d);$r=$s*.62;
        return [
            ['xp',$c->add($reach,0,0),0,0,1],['xm',$c->add(-$reach,0,0),0,0,-1],
            ['yp',$c->add(0,$reach,0),1,1,1],['ym',$c->add(0,-$reach,0),2,1,-1],
            ['zp',$c->add(0,0,$reach),0,2,1],['zm',$c->add(0,0,-$reach),0,2,-1],
            ['rx',$c->add($r,0,0),3,0,1],['ry',$c->add(0,$r,0),3,1,1],['rz',$c->add(0,0,$r),3,2,1],
            ['sc',$c->add($r*.9,$r*.9,$r*.9),4,3,1],
        ];
    }
    private function editorCenter(DisplayEntity $d):Vector3{return $d->getPosition()->add(0,$d->getDisplaySize()/2,0);}
    public function refreshHandles(Player $p):void{
        $d=$this->selected($p);if($d===null){$this->removeHandles($p);return;}$this->removeHandles($p);$mode=$d->getMode();$items=[];
        foreach($this->handleDefinitions($d) as $it){
            $show=($mode===1&&$it[2]<=2)||($mode===2&&$it[2]===3)||($mode===3&&$it[2]===4);if(!$show)continue;
            [$name,$pos,$kind,$axis,$sign]=$it;$h=$this->newHandle($p->getWorld(),$pos,$kind,$axis,$sign,$d->getId());$h->spawnTo($p);$items[]=$h->getId();
        }
        $this->handles[$p->getId()]=$items;
    }
    private function newHandle(World $w,Vector3 $pos,int $kind,int $axis,int $sign,int $owner):HandleEntity{
        $h=new HandleEntity(new Location($pos->x,$pos->y,$pos->z,$w,0.0,0.0),null);$h->kind=$kind;$h->axis=$axis;$h->sign=$sign;$h->ownerDisplayId=$owner;$h->color=$kind===4?3:$axis;$h->setScale(1.0);
        if($kind===0){$yaw=$axis===0?($sign>0?-90.0:90.0):($axis===2?($sign>0?180.0:0.0):0.0);$h->setRotation($yaw,0.0);}
        elseif($kind===1){$h->setRotation(0.0,$sign>0?0.0:180.0);}
        return $h;
    }
    public function removeHandles(Player $p):void{
        $ids=$this->handles[$p->getId()]??[];if($ids!==[]){foreach($this->plugin->getServer()->getWorldManager()->getWorlds() as $w)foreach($w->getEntities() as $e)if($e instanceof HandleEntity&&in_array($e->getId(),$ids,true))$e->flagForDespawn();}unset($this->handles[$p->getId()]);
    }
    public function updateHandlePositions(Player $p):void{
        $d=$this->selected($p);if($d===null)return;$wanted=[];foreach($this->handleDefinitions($d) as $it)$wanted[$it[0]]=$it[1];
        foreach($p->getWorld()->getEntities() as $e){if(!$e instanceof HandleEntity||$e->ownerDisplayId!==$d->getId())continue;$key=$this->handleKey($e);if($key!==null&&isset($wanted[$key]))$e->teleport(new Location($wanted[$key]->x,$wanted[$key]->y,$wanted[$key]->z,$p->getWorld(),$e->getLocation()->yaw,$e->getLocation()->pitch));}
    }
    private function handleKey(HandleEntity $h):?string{
        if($h->kind===0)return $h->axis===0?($h->sign>0?'xp':'xm'):($h->axis===2?($h->sign>0?'zp':'zm'):null);
        if($h->kind===1)return $h->sign>0?'yp':'ym';
        if($h->kind===2)return 'ym';
        if($h->kind===3)return ['rx','ry','rz'][$h->axis]??null;
        if($h->kind===4)return 'sc';return null;
    }
    public function findOwnerDisplay(ModelFaceEntity $m):?DisplayEntity{foreach($m->getWorld()->getEntities() as $e)if($e instanceof DisplayEntity&&$e->getId()===$m->ownerDisplayId&&!$e->isClosed())return $e;return null;}
    public function clearAllHandles():void{
        $this->playerControls=[];
        foreach($this->handles as $playerId=>$ids){foreach($this->plugin->getServer()->getWorldManager()->getWorlds() as $w)foreach($w->getEntities() as $e)if($e instanceof HandleEntity&&in_array($e->getId(),$ids,true))$e->flagForDespawn();} $this->handles=[];$this->drags=[];}
    public function listDisplays(Player $p):array{$a=[];foreach($p->getWorld()->getEntities() as $e)if($e instanceof DisplayEntity&&!$e->isClosed())$a[]=$e;return $a;}

    public function startPlayerControl(Player $p,DisplayEntity $d):void{
        $this->stopDrag($p);
        $this->removeHandles($p);
        $this->selected[$p->getName()]=$d->getId();
        $d->setInvisible(false);
        $d->setMode(1);
        $this->playerControls[$p->getId()]=['displayId'=>$d->getId(),'last'=>$p->getPosition()->asVector3()];
        $this->syncModel($d,0);
        $p->sendMessage('§aPlayer Control §fACTIVE§a. Walk, strafe, jump or sneak to move the block. Use the menu again to stop.');
    }

    public function stopPlayerControl(Player $p,bool $message=true):void{
        if(isset($this->playerControls[$p->getId()])){
            unset($this->playerControls[$p->getId()]);
            if($message)$p->sendMessage('§7Player Control stopped.');
        }
    }

    public function hasPlayerControl(Player $p):bool{return isset($this->playerControls[$p->getId()]);}

    public function getPlayerControlDisplay(Player $p):?DisplayEntity{
        $c=$this->playerControls[$p->getId()]??null;
        if(!is_array($c))return null;
        return $this->displayById($p,(int)($c['displayId']??-1));
    }

    /**
     * Sends a client-only collision cell. This never changes the server world.
     * Altay's UpdateBlockPacket is used directly so the Bedrock client runs its
     * native block collision against the fake cell, while the real world remains untouched.
     */
    private function sendClientCollisionCell(Player $p, int $x, int $y, int $z, int $runtimeId) : void{
        $p->getNetworkSession()->sendDataPacket(UpdateBlockPacket::create(
            BlockPosition::fromVector3(new Vector3($x, $y, $z)),
            $runtimeId,
            UpdateBlockPacket::FLAG_NETWORK,
            UpdateBlockPacket::DATA_LAYER_NORMAL
        ));
    }

    private function realBlockRuntimeId(World $world, int $x, int $y, int $z) : int{
        $block = $world->getBlockAt($x, $y, $z);
        return TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($block->getStateId());
    }

    /** @return array<string,array{0:int,1:int,2:int}> */
    private function desiredClientSolidCells(Player $p) : array{
        $world = $p->getWorld();
        $playerBox = $p->getBoundingBox()->expandedCopy(self::CLIENT_COLLISION_RADIUS, 32.0, self::CLIENT_COLLISION_RADIUS);
        $cells = [];
        foreach($world->getNearbyEntities($playerBox, $p) as $entity){
            if(!$entity instanceof DisplayEntity || $entity->isClosed() || !$entity->isSolid()) continue;
            if($this->hasPlayerControl($p) && $this->getPlayerControlDisplay($p)?->getId() === $entity->getId()) continue;
            [$minX,$minY,$minZ,$maxX,$maxY,$maxZ] = $this->solidCollisionBounds($entity);
            $sx = (int)floor($minX + 0.000001);
            $sy = (int)floor($minY + 0.000001);
            $sz = (int)floor($minZ + 0.000001);
            $ex = (int)ceil($maxX - 0.000001) - 1;
            $ey = (int)ceil($maxY - 0.000001) - 1;
            $ez = (int)ceil($maxZ - 0.000001) - 1;

            // Fake cells are a client-side supplement. For very small or extreme shapes,
            // the server-side AABB resolver remains the precision fallback.
            for($y=$sy; $y<=$ey; ++$y){
                for($x=$sx; $x<=$ex; ++$x){
                    for($z=$sz; $z<=$ez; ++$z){
                        $key=$x.':'.$y.':'.$z;
                        if(isset($cells[$key])) continue;
                        // Never replace an existing colliding world block with a fake block.
                        $worldBlock=$world->getBlockAt($x,$y,$z);
                        if($worldBlock->getCollisionBoxes() !== []) continue;
                        $cells[$key]=[$x,$y,$z];
                        if(count($cells) >= self::MAX_CLIENT_SOLID_CELLS) break 3;
                    }
                }
            }
        }
        return $cells;
    }

    public function invalidateClientSolidCollision(Player $p) : void{
        $pid=$p->getId();
        unset($this->clientSolidCells[$pid],$this->clientSolidWorld[$pid],$this->clientSolidChunk[$pid]);
    }

    public function refreshClientSolidCollision(Player $p) : void{
        if(!$p->isConnected()) return;
        $pid=$p->getId();
        $world=$p->getWorld();
        $worldId=$world->getId();
        $chunkKey=((int)floor($p->getPosition()->x)>>4).':'.((int)floor($p->getPosition()->z)>>4);

        // Bedrock rebuilds the client-side world state after a dimension/world change.
        // The previous collision snapshot therefore cannot be treated as still present.
        // The same applies when the player crosses into a new chunk: chunks may have been
        // unloaded/reloaded on the client, which clears packet-only collision cells.
        $worldChanged=($this->clientSolidWorld[$pid]??null)!==$worldId;
        $chunkChanged=($this->clientSolidChunk[$pid]??null)!==$chunkKey;
        $old=$this->clientSolidCells[$pid]??[];
        if($worldChanged){
            // Never try to restore the previous world's fake cells into the new world.
            // The world/dimension transition replaces the client's visible chunk state.
            $old=[];
            $this->clientSolidWorld[$pid]=$worldId;
            $this->clientSolidChunk[$pid]=$chunkKey;
        }elseif($chunkChanged){
            // Same world, but the client may have unloaded/reloaded nearby chunks.
            // Remove stale cells from the previous snapshot, then resend every desired
            // cell so collision survives client-side chunk rebuilds.
            $this->clientSolidWorld[$pid]=$worldId;
            $this->clientSolidChunk[$pid]=$chunkKey;
        }

        $desired=$this->desiredClientSolidCells($p);
        $barrierRuntime=TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId(
            VanillaBlocks::BARRIER()->getStateId()
        );

        foreach($desired as $key => [$x,$y,$z]){
            if($worldChanged || $chunkChanged || !isset($old[$key])){
                $this->sendClientCollisionCell($p,$x,$y,$z,$barrierRuntime);
            }
        }
        if(!$worldChanged){
            foreach($old as $key => [$x,$y,$z]){
                if(!isset($desired[$key])){
                    $this->sendClientCollisionCell($p,$x,$y,$z,$this->realBlockRuntimeId($world,$x,$y,$z));
                }
            }
        }
        $this->clientSolidCells[$pid]=$desired;
        $this->syncVirtualGroundState($p);
    }

    public function clearClientSolidCollision(Player $p) : void{
        $pid=$p->getId();
        $old=$this->clientSolidCells[$pid]??[];
        unset($this->clientSolidCells[$pid],$this->clientSolidWorld[$pid],$this->clientSolidChunk[$pid]);
        if($old===[]) return;
        // On quit there is no client state that needs restoring. Avoid writing those
        // packet-only cells into whichever world happens to be current.
    }

    public function syncVirtualGroundState(Player $p) : void{
        $bb=$p->getBoundingBox();
        $feet=$bb->minY;
        foreach($p->getWorld()->getNearbyEntities($bb->expandedCopy(3.0,1.25,3.0),$p) as $entity){
            if(!$entity instanceof DisplayEntity || $entity->isClosed() || !$entity->isSolid()) continue;
            if($this->hasPlayerControl($p) && $this->getPlayerControlDisplay($p)?->getId()===$entity->getId()) continue;
            [$minX,$minY,$minZ,$maxX,$maxY,$maxZ]=$this->solidCollisionBounds($entity);
            if($bb->maxX > $minX + 0.001 && $bb->minX < $maxX - 0.001 &&
               $bb->maxZ > $minZ + 0.001 && $bb->minZ < $maxZ - 0.001 &&
               $feet >= $maxY - 0.10 && $feet <= $maxY + 0.16){
                $p->onGround=true;
                $p->isCollidedVertically=true;
                $p->isCollided=true;
                $p->resetFallDistance();
                return;
            }
        }
    }

    /**
     * Resolves BDBlockDisplay's Solid/Walkable state without placing real world blocks.
     * Altay's Player::move() only queries World::getBlockCollisionBoxes(), so entity
     * collision flags alone cannot make a custom display a platform. We therefore
     * constrain the PlayerMoveEvent destination against the display's virtual AABB.
     */
    public function resolveSolidMovement(PlayerMoveEvent $ev):void{
        $p=$ev->getPlayer();
        $from=$ev->getFrom();$to=$ev->getTo();
        if($from->getWorld() !== $to->getWorld())return;
        $width=$p->getSize()->getWidth();$height=$p->getSize()->getHeight();$half=$width/2;
        $wantedX=$to->x-$from->x;$wantedY=$to->y-$from->y;$wantedZ=$to->z-$from->z;
        if(abs($wantedX)<1e-8&&abs($wantedY)<1e-8&&abs($wantedZ)<1e-8)return;
        if(($wantedX*$wantedX+$wantedY*$wantedY+$wantedZ*$wantedZ)>225)return;

        $fromBB=new AxisAlignedBB($from->x-$half,$from->y,$from->z-$half,$from->x+$half,$from->y+$height,$from->z+$half);
        $sweep=$fromBB->addCoord($wantedX,$wantedY,$wantedZ)->expandedCopy(1.75,1.75,1.75);
        $boxes=[];
        foreach($p->getWorld()->getNearbyEntities($sweep,$p) as $entity){
            if(!$entity instanceof DisplayEntity || $entity->isClosed() || !$entity->isSolid())continue;
            foreach($this->solidCollisionBoxes($entity) as $box)$boxes[]=$box;
        }
        if($boxes===[])return;

        // Mirror Altay Entity::move()'s Y -> X -> Z AABB resolution order, but apply it
        // to BDBlockDisplay virtual collision boxes after PlayerMoveEvent.
        $moveBB=clone $fromBB;
        $dy=$wantedY;
        foreach($boxes as $box)$dy=$box->calculateYOffset($moveBB,$dy);
        $moveBB->offset(0,$dy,0);
        $dx=$wantedX;
        foreach($boxes as $box)$dx=$box->calculateXOffset($moveBB,$dx);
        $moveBB->offset($dx,0,0);
        $dz=$wantedZ;
        foreach($boxes as $box)$dz=$box->calculateZOffset($moveBB,$dz);
        $moveBB->offset(0,0,$dz);

        $x=($moveBB->minX+$moveBB->maxX)/2;$y=$moveBB->minY;$z=($moveBB->minZ+$moveBB->maxZ)/2;
        $horizontalCollision=($dx!==$wantedX)||($dz!==$wantedZ);
        $verticalCollision=($dy!==$wantedY);
        $virtualGround=false;
        foreach($boxes as $box){
            $horizontal=($moveBB->maxX>$box->minX+0.001&&$moveBB->minX<$box->maxX-0.001&&$moveBB->maxZ>$box->minZ+0.001&&$moveBB->minZ<$box->maxZ-0.001);
            if($horizontal && abs($moveBB->minY-$box->maxY)<=0.03125){$virtualGround=true;break;}
        }
        if($virtualGround){
            $p->onGround=true;
            $p->isCollidedVertically=true;
            $p->isCollided=$p->isCollided||$verticalCollision;
            $p->resetFallDistance();
        }else{
            // Preserve Altay's normal state for leaving the virtual surface.
            $p->onGround=$p->isOnGround() && !$verticalCollision ? true : $p->isOnGround();
            if($verticalCollision)$p->isCollidedVertically=true;
            if($horizontalCollision)$p->isCollidedHorizontally=true;
            $p->isCollided=$p->isCollided||$verticalCollision||$horizontalCollision;
        }

        $changed=abs($x-$to->x)>0.000001||abs($y-$to->y)>0.000001||abs($z-$to->z)>0.000001;
        if($changed){
            $ev->setTo(new Location($x,$y,$z,$to->getWorld(),$to->yaw,$to->pitch));
        }
    }

    /** @return AxisAlignedBB[] */
    private function solidCollisionBoxes(DisplayEntity $d):array{
        $l=$d->getLocation();$s=$d->getDisplaySize();$row=BlockRegistry::row($d->getBlockId());$shape=$row[0]??1;
        $minX=$l->x-$s/2;$maxX=$l->x+$s/2;$minZ=$l->z-$s/2;$maxZ=$l->z+$s/2;$minY=$l->y;$maxY=$l->y+$s;
        if($shape===2){
            if($d->getVariant()===1)$minY+=$s/2;else $maxY-=$s/2;
        }
        return [new AxisAlignedBB($minX,$minY,$minZ,$maxX,$maxY,$maxZ)];
    }

    /** @return array{0:float,1:float,2:float,3:float,4:float,5:float} */
    private function solidCollisionBounds(DisplayEntity $d):array{
        $l=$d->getLocation();
        $s=$d->getDisplaySize();
        $minX=$l->x-$s/2.0;
        $maxX=$l->x+$s/2.0;
        $minZ=$l->z-$s/2.0;
        $maxZ=$l->z+$s/2.0;
        $row=BlockRegistry::row($d->getBlockId());
        $shape=$row[0]??1;
        $minY=$l->y;
        $maxY=$l->y+$s;
        if($shape===2){
            // Variant 0 = bottom slab, variant 1 = top slab.
            if($d->getVariant()===1){
                $minY+=$s/2.0;
            }else{
                $maxY-=$s/2.0;
            }
        }
        return [$minX,$minY,$minZ,$maxX,$maxY,$maxZ];
    }

    public function tickPlayerControl(\pocketmine\event\player\PlayerMoveEvent $ev):void{
        $p=$ev->getPlayer();
        $id=$p->getId();$control=$this->playerControls[$id]??null;
        if(!is_array($control))return;
        $d=$this->displayById($p,(int)($control['displayId']??-1));
        if($d===null||$d->getWorld()!==$p->getWorld()){ $this->stopPlayerControl($p,false); return; }
        $from=$ev->getFrom();$to=$ev->getTo();
        $delta=new Vector3($to->x-$from->x,$to->y-$from->y,$to->z-$from->z);
        $dist2=$delta->lengthSquared();
        if($dist2<0.00000001)return;
        // Walking, strafing, jump and sneak movement is transferred 1:1.
        // Ignore teleport-sized jumps so a server teleport cannot drag a display across the world.
        if($dist2>25.0){return;}
        $l=$d->getLocation();
        $np=new Vector3($l->x+$delta->x,$l->y+$delta->y,$l->z+$delta->z);
        $d->teleport($np,$l->yaw,$l->pitch);
        $this->playerControls[$id]['last']=$to->asVector3();
        // Player-control runs on PlayerMoveEvent, so move existing companion
        // model faces directly instead of rebuilding/spawning them every move.
        foreach($d->getWorld()->getEntities() as $e){
            if($e instanceof ModelFaceEntity && !$e->isClosed() && $e->ownerDisplayId===$d->getId()){
                $ml=$e->getLocation();
                $e->teleport($np,$ml->yaw,$ml->pitch);
            }
        }
    }

    public function startDrag(Player $p,HandleEntity $h):void{
        $d=$this->displayById($p,$h->ownerDisplayId);if($d===null)return;
        $current=$this->drags[$p->getId()]??null;if(is_array($current)&&($current['handleId']??-1)===$h->getId()){$this->stopDrag($p);$p->sendMessage('§7Drag stopped.');return;}
        $this->stopDrag($p);
        $rayOrigin=$p->getEyePos();$rayDir=$p->getDirectionVector();$center=$this->editorCenter($d);
        if($h->kind===3){
            $axis=$this->axisVector($h->axis);$v=$this->rayPlaneVector($rayOrigin,$rayDir,$center,$axis);if($v===null)$v=$this->fallbackPlaneVector($h->axis);
            $this->drags[$p->getId()]=['displayId'=>$d->getId(),'handleId'=>$h->getId(),'type'=>'rotate','axis'=>$h->axis,'lastVector'=>$v,'lastTick'=>$this->plugin->getServer()->getTick()];
        }elseif($h->kind===4){
            $axis=new Vector3(1/sqrt(3),1/sqrt(3),1/sqrt(3));$t=$this->rayLineParameter($rayOrigin,$rayDir,$center,$axis);
            $this->drags[$p->getId()]=['displayId'=>$d->getId(),'handleId'=>$h->getId(),'type'=>'scale','axis'=>3,'startT'=>$t,'startSize'=>$d->getDisplaySize(),'center'=>$center,'lastTick'=>$this->plugin->getServer()->getTick()];
        }else{
            $axis=$this->axisVector($h->axis);$t=$this->rayLineParameter($rayOrigin,$rayDir,$center,$axis);
            $this->drags[$p->getId()]=['displayId'=>$d->getId(),'handleId'=>$h->getId(),'type'=>'move','axis'=>$h->axis,'startT'=>$t,'startPos'=>$d->getPosition()->asVector3(),'center'=>$center,'lastTick'=>$this->plugin->getServer()->getTick()];
        }
        $h->setHot(true);$p->sendMessage('§aDrag active. Move your view to edit; tap the same handle again to stop.');
    }
    public function stopDrag(Player $p):void{
        $drag=$this->drags[$p->getId()]??null;if(is_array($drag)){foreach($p->getWorld()->getEntities() as $e)if($e instanceof HandleEntity&&$e->getId()===(int)($drag['handleId']??-1)){$e->setHot(false);break;}}unset($this->drags[$p->getId()]);
    }
    public function hasActiveDrag(Player $p):bool{return isset($this->drags[$p->getId()]);}
    public function tickDrag(Player $p):void{
        $drag=$this->drags[$p->getId()]??null;if(!is_array($drag))return;$d=$this->displayById($p,(int)($drag['displayId']??-1));$h=$this->entityById($p,(int)($drag['handleId']??-1));
        if($d===null||$h===null){$this->stopDrag($p);return;}
                $origin=$p->getEyePos();$dir=$p->getDirectionVector();$center=$this->editorCenter($d);
        if($drag['type']==='move'){
            $center=$drag['center'];$axis=$this->axisVector((int)$drag['axis']);$t=$this->rayLineParameter($origin,$dir,$center,$axis);$delta=DisplayMath::clamp($t-(float)$drag['startT'],-4.0,4.0);$base=$drag['startPos'];$np=$base->addVector($axis->multiply($delta));
            if($np->distanceSquared($d->getPosition())>0.0000005)$d->teleport(new Location($np->x,$np->y,$np->z,$d->getWorld(),$d->getLocation()->yaw,$d->getLocation()->pitch));
        }elseif($drag['type']==='rotate'){
            $axis=$this->axisVector((int)$drag['axis']);$v=$this->rayPlaneVector($origin,$dir,$center,$axis);if($v===null)$v=$drag['lastVector'];if(DisplayMath::length($v)<1e-6)return;$v=DisplayMath::unit($v);$prev=$drag['lastVector'];$cross=DisplayMath::cross($prev,$v);$angle=atan2(DisplayMath::dot($axis,$cross),DisplayMath::dot($prev,$v));$angle=DisplayMath::clamp(rad2deg($angle),-20.0,20.0);
            if(abs($angle)>0.001){$this->rotateAxis($d,(int)$drag['axis'],$angle);$this->drags[$p->getId()]['lastVector']=$v;}
        }else{
            $center=$drag['center'];$axis=new Vector3(1/sqrt(3),1/sqrt(3),1/sqrt(3));$t=$this->rayLineParameter($origin,$dir,$center,$axis);$delta=DisplayMath::clamp($t-(float)$drag['startT'],-2.0,2.0);$size=DisplayMath::clamp((float)$drag['startSize']+$delta/(.75*sqrt(3)),.0625,16.0);$d->setDisplaySize($size);
        }
        $this->syncModel($d,0);$this->updateHandlePositions($p);
    }
    private function axisVector(int $axis):Vector3{return $axis===0?new Vector3(1,0,0):($axis===1?new Vector3(0,1,0):new Vector3(0,0,1));}
    private function fallbackPlaneVector(int $axis):Vector3{return $axis===0?new Vector3(0,0,1):new Vector3(1,0,0);}
    private function rayPlaneVector(Vector3 $origin,Vector3 $dir,Vector3 $planePoint,Vector3 $normal):?Vector3{
        $den=DisplayMath::dot($dir,$normal);if(abs($den)<1e-6)return null;$t=DisplayMath::dot(DisplayMath::sub($planePoint,$origin),$normal)/$den;if($t<0)return null;$hit=$origin->addVector($dir->multiply($t));$v=DisplayMath::sub($hit,$planePoint);return DisplayMath::length($v)<1e-6?null:DisplayMath::unit($v);
    }
    private function rayLineParameter(Vector3 $origin,Vector3 $dir,Vector3 $linePoint,Vector3 $axis):float{
        $w0=DisplayMath::sub($origin,$linePoint);$a=DisplayMath::dot($dir,$dir);$b=DisplayMath::dot($dir,$axis);$c=DisplayMath::dot($axis,$axis);$d=DisplayMath::dot($dir,$w0);$e=DisplayMath::dot($axis,$w0);$den=$a*$c-$b*$b;
        if(abs($den)<1e-7)return -$e/$c;return ($a*$e-$b*$d)/$den;
    }
    private function displayById(Player $p,int $id):?DisplayEntity{foreach($p->getWorld()->getEntities() as $e)if($e instanceof DisplayEntity&&$e->getId()===$id&&!$e->isClosed())return $e;return null;}
    private function entityById(Player $p,int $id):?HandleEntity{foreach($p->getWorld()->getEntities() as $e)if($e instanceof HandleEntity&&$e->getId()===$id&&!$e->isClosed())return $e;return null;}

    public function applyHandle(Player $p,HandleEntity $h):void{
        if($h->kind===3||$h->kind===4||$h->kind===0||$h->kind===1||$h->kind===2){$this->startDrag($p,$h);return;}
    }
    public function nudge(DisplayEntity $d,int $axis,float $amount):void{$l=$d->getLocation();$x=$l->x;$y=$l->y;$z=$l->z;if($axis===0)$x+=$amount;elseif($axis===1)$y+=$amount;else $z+=$amount;$d->teleport(new Location($x,$y,$z,$d->getWorld(),$l->yaw,$l->pitch));}
    private function rotateAxis(DisplayEntity $d,int $axis,float $delta):void{$loc=$d->getLocation();$rx=$loc->pitch;$ry=$loc->yaw;$rz=$d->getRoll();if($axis===0)$rx+=$delta;elseif($axis===1)$ry+=$delta;else $rz+=$delta;$d->setRotation($ry,$rx);$d->setRoll($rz);}
    public function rotateBy(DisplayEntity $d,int $axis,float $delta):void{$this->rotateAxis($d,$axis,$delta);}
    public function syncModel(DisplayEntity $d,int $moveCode=0):void{
        $row=BlockRegistry::row($d->getBlockId());if($row===null)return;$shape=$row[0];$faceTex=array_slice($row,1,6);$scale=$d->getDisplaySize();$names=['up','down','north','south','east','west'];
        $r=$d->getLocation();$v=max(0,min(BlockRegistry::variantCount($shape)-1,$d->getVariant()));if($d->getVariant()!==$v)$d->setVariant($v);
        $existing=[];foreach($d->getWorld()->getEntities() as $e)if($e instanceof ModelFaceEntity&&!$e->isClosed()&&$e->ownerDisplayId===$d->getId())$existing[$e::getNetworkTypeId()]=$e;$wanted=[];
        foreach($names as $i=>$face){$class=ModelEntityRegistry::classFor($shape,$v,$face);if($class===null)continue;$net=$class::getNetworkTypeId();$m=$existing[$net]??null;if($m===null){$m=new $class(new Location($r->x,$r->y,$r->z,$r->getWorld(),$r->yaw,$r->pitch),null);}else{$m->teleport(new Location($r->x,$r->y,$r->z,$r->getWorld(),$r->yaw,$r->pitch));}$m->configure($faceTex[$i],$shape,$v,$face,$scale,$r->yaw,$r->pitch,$d->getRoll(),$d->getId(),$moveCode);$m->spawnToAll();$wanted[$m->getId()]=true;}
        foreach($existing as $m)if(!isset($wanted[$m->getId()]))$m->flagForDespawn();$d->syncModelMetadata();
    }
    public function deleteDisplay(DisplayEntity $d):void{foreach($d->getWorld()->getEntities() as $e)if($e instanceof ModelFaceEntity&&$e->ownerDisplayId===$d->getId())$e->flagForDespawn();$d->flagForDespawn();}
    public function duplicate(Player $p,DisplayEntity $d):DisplayEntity{$pos=$d->getPosition()->add($d->getDisplaySize(),0,0);$n=new DisplayEntity(new Location($pos->x,$pos->y,$pos->z,$p->getWorld(),$d->getLocation()->yaw,$d->getLocation()->pitch),null);$n->setBlockId($d->getBlockId());$n->setDisplaySize($d->getDisplaySize());$n->setRoll($d->getRoll());$n->setSolid($d->isSolid());$n->spawnToAll();$this->syncModel($n);return $n;}
}
