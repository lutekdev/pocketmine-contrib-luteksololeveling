<?php

declare( strict_types = 1 );

namespace LutekDev\LutekSoloLeveling\skills\list;

use LutekDev\LutekSoloLeveling\core\PlayerSession;
use LutekDev\LutekSoloLeveling\skills\Skill;
use pocketmine\player\Player;
use pocketmine\world\particle\FlameParticle;

class DashSkill extends Skill {
    public function __construct () {
        parent::__construct( "Dash", 20, 2.0 );
    }
    
    public function cast ( Player $player, PlayerSession $session ) : bool {
        // Verifica MP
        if (!$session->consumeMp( $this->manaCost )) {
            $player->sendTip( "§cSem Mana!" );
            return false;
        }
        
        // Lógica do Dash: Vetor de direção multiplicado
        $direction = $player->getDirectionVector();
        $player->setMotion( $direction->multiply( 2.5 ) ); // Impulso forte
        
        // Efeito Visual
        $player->getWorld()->addParticle( $player->getPosition(), new FlameParticle() );
        $player->sendMessage( "§aVocê usou Dash!" );
        
        return true;
    }
}