<?php

declare( strict_types = 1 );

namespace LutekDev\LutekSoloLeveling\events;

use LutekDev\LutekSoloLeveling\Main;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\world\particle\CriticalParticle;

readonly class EventListener implements Listener {
    public function __construct ( private Main $plugin ) {}
    
    // Gerencia a criação da Sessão
    public function onJoin ( PlayerJoinEvent $event ) : void {
        $this->plugin->createSession( $event->getPlayer() );
    }
    
    public function onQuit ( PlayerQuitEvent $event ) : void {
        $session = $this->plugin->getSession( $event->getPlayer() );
        $session?->save();
        $this->plugin->removeSession( $event->getPlayer() );
    }
    
    // Aplica o Dano (Strength) e Defesa (Defense)
    public function onDamage ( EntityDamageEvent $event ) : void {
        $entity = $event->getEntity();
        
        // 1. CÁLCULO DE DEFESA (Quando o JOGADOR apanha)
        if ($entity instanceof Player) {
            $session = $this->plugin->getSession( $entity );
            
            if ($session !== null) {
                $defenseFactor = $session->getDefenseFactor();
                
                // Redução de dano simples baseada em defesa
                // Fórmula de redução percentual
                // Dano * (1 - (defesa / 100))
                $newDamage = $event->getBaseDamage() * ( 1 - ( $defenseFactor / 100 ) );
                $event->setBaseDamage( $newDamage );
            }
        }
        
        // 2. CÁLCULO DE ATAQUE (Quando o JOGADOR bate)
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            
            if ($damager instanceof Player && $entity instanceof Living) {
                $session = $this->plugin->getSession( $damager );
                
                if ($session !== null) {
                    $damage = $session->getAttackDamage();
                    
                    // SISTEMA DE  CRÍTICO
                    $chance = $session->getCritChance();
                    $roll = mt_rand( 0, 1000 ) / 10;
                    
                    if ($roll <= $chance) {
                        // CRÍTICO CONFIRMADO
                        $damage *= 1.5;
                        
                        // Efeitos Visuais (Partículas)
                        // Gera partículas ao redor da entidade atingida
                        $entity->getWorld()->addParticle( $entity->getPosition()->add( 0, 1, 0 ), new CriticalParticle() );
                        
                        
                        // Feedback Sonoro (Som metálico pesado)
                        $damager->getNetworkSession()->sendDataPacket( \pocketmine\network\mcpe\protocol\PlaySoundPacket::create( "random.anvil_land", $entity->getPosition()->x, $entity->getPosition()->y, $entity->getPosition()->z, 0.5, 2.0 // Volume, Pitch (Agudo)
                        ) );
                        
                        $damager->sendTip( "§c§lCRÍTICO!" );
                    }
                    
                    $event->setModifier( $damage, EntityDamageEvent::MODIFIER_STRENGTH );
                    $session->addFatigue( 0.5 );
                }
            }
        }
    }
}