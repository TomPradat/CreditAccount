<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace CreditAccount\EventListeners;

use CreditAccount\CreditAccount;
use CreditAccount\Event\CreditAccountEvent;
use CreditAccount\Model\CreditAccountQuery;
use CreditAccount\Model\CreditAccount as CreditAccountModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;


/**
 * Class CreditEventListener
 * @package CreditAccount\EventListeners
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CreditEventListener implements EventSubscriberInterface
{

    /**
     * @var \Thelia\Core\HttpFoundation\Request
     */
    protected $request;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function addAmount(CreditAccountEvent $event)
    {
        $customer = $event->getCustomer();

        $creditAccount = CreditAccountQuery::create()
            ->filterByCustomerId($customer->getId())
            ->findOne();
        ;

        if (null === $creditAccount) {
            $creditAccount = (new CreditAccountModel())
                ->setCustomerId($customer->getId());
        }

        $creditAccount->addAmount($event->getAmount())
            ->save();

        $event->setCreditAccount($creditAccount);
    }

    public function verifyCreditUsage(OrderEvent $event)
    {
        $session = $this->request->getSession();

        if ($session->get('creditAccount.used') == 1) {
            $customer = $event->getOrder()->getCustomer();
            $amount = $session->get('creditAccount.amount');

            $creditEvent = new CreditAccountEvent($customer, ($amount*-1));
            $event->getDispatcher()->dispatch(CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT, $creditEvent);

            $session->set('creditAccount.used', 0);
            $session->set('creditAccount.amount', 0);

        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return [
            CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT => ['addAmount', 128],
            TheliaEvents::ORDER_BEFORE_PAYMENT => ['verifyCreditUsage', 128]
        ];
    }
}