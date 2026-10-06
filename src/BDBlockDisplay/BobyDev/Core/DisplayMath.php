<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Core;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;

final class DisplayMath{
    public static function clamp(float $v,float $min,float $max): float{return max($min,min($max,$v));}
    public static function add(Vector3 $a, Vector3 $b): Vector3{return new Vector3($a->x+$b->x,$a->y+$b->y,$a->z+$b->z);}
    public static function sub(Vector3 $a, Vector3 $b): Vector3{return new Vector3($a->x-$b->x,$a->y-$b->y,$a->z-$b->z);}
    public static function mul(Vector3 $a,float $n): Vector3{return new Vector3($a->x*$n,$a->y*$n,$a->z*$n);}
    public static function dot(Vector3 $a,Vector3 $b):float{return $a->x*$b->x+$a->y*$b->y+$a->z*$b->z;}
    public static function cross(Vector3 $a,Vector3 $b):Vector3{return new Vector3($a->y*$b->z-$a->z*$b->y,$a->z*$b->x-$a->x*$b->z,$a->x*$b->y-$a->y*$b->x);}
    public static function length(Vector3 $a):float{return sqrt(self::dot($a,$a));}
    public static function unit(Vector3 $a):Vector3{$l=self::length($a);return $l<1.0e-8?new Vector3(0,0,0):self::mul($a,1/$l);}
    public static function center(Entity $e): Vector3{return $e->getPosition()->add(0,$e->getSize()->getHeight()*$e->getScale()/2,0);}
    public static function rotateLocal(Vector3 $v,float $yaw,float $pitch,float $roll):Vector3{
        $y=deg2rad($yaw);$p=deg2rad($pitch);$r=deg2rad($roll);
        $cy=cos($y);$sy=sin($y);$cp=cos($p);$sp=sin($p);$cr=cos($r);$sr=sin($r);
        // Preserve BlockDisplay's Y -> X -> Z rotation order.
        $x1=$v->x*$cy+$v->z*$sy;$z1=-$v->x*$sy+$v->z*$cy;$y1=$v->y;
        $y2=$y1*$cp-$z1*$sp;$z2=$y1*$sp+$z1*$cp;$x2=$x1;
        return new Vector3($x2*$cr-$y2*$sr,$x2*$sr+$y2*$cr,$z2);
    }
}
