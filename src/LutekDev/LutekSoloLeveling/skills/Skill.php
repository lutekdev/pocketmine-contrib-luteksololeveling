<?php

declare( strict_types = 1 );

namespace LutekDev\LutekSoloLeveling\skills;

use LutekDev\LutekSoloLeveling\core\PlayerSession;
use pocketmine\player\Player;

abstract class Skill {
    public function __construct ( protected readonly string $name, protected readonly int $manaCost, protected float $cooldown ) {}
    
    public function getName () : string { return $this->name; }
    
    public function getManaCost () : int { return $this->manaCost; }
    
    /**
     * A lógica da habilidade vai aqui.
     * Retorna true se a skill foi usada com sucesso.
     */
    abstract public function cast ( Player $player, PlayerSession $session ) : bool;
}