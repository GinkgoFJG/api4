<?php

namespace Civi\API\V4\Event\Subscriber;

use Civi\API\Event\PrepareEvent;
use Civi\API\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractPrepareSubscriber implements EventSubscriberInterface {
  /**
   * @return array
   */
  public static function getSubscribedEvents() {
    return array(
      Events::PREPARE => 'onApiPrepare'
    );
  }

  /**
   * @param PrepareEvent $event
   */
  abstract public function onApiPrepare(PrepareEvent $event);
}