<?php

/**
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Eep\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Eep\Form\InscriptionOrderForm as IOF;
//SERVICES
use Eep\Service\InscriptionManager;
use Eep\Service\OrderManager;
use Eep\Service\UserManager;
use Eep\Entity\Result as R;
use Eep\Service\LogManager as LM;
//OTHERS
use Eep\ValueObject\Message;
use Eep\Entity\Order;

class InscriptionController extends AbstractActionController {

    private $orderManager;
    private $inscriptionManager;
    private $userManager;

    public function __construct(OrderManager $orderManager, InscriptionManager $inscriptionManager, UserManager $userManager) {
        $this->inscriptionManager = $inscriptionManager;
        $this->orderManager = $orderManager;
        $this->userManager = $userManager;
    }

    private function getInscriptionView($msg, $enable) {
        $view = new ViewModel([
            'msg' => $msg,
            'enable' => $enable
        ]);
        $view->setTemplate('eep/inscription/inscription');
        return $view;
    }

    private function canCreateInscription(): R {
        $res = new R();
        $userCode = $this->identity();
        $result = $this->inscriptionManager->isInscriptionValid($userCode);
        $status = $result->getObj();
        //var_dump($status); die;
        if ($result->get() == false || $status == InscriptionManager::FIRST_YEAR_AUTH) {
            //NOT INSCRIBED YET
            $result = $this->inscriptionManager->isUserFirstYear($userCode);
            if ($result->get() == true) {//IS USER FIRST YEAR
                $result = $this->orderManager->getUserOrder($userCode, null, null, Order::INSCRIPTION, date('Y'));
                if ($result->get() == false) {
                    $res->failure($result);
                } else {
                    $orders = $result->getObj();
                    if (count($orders) > 0) {
                        $order = array_pop($orders);
                        $url = $this->url()->fromRoute('order', ['id' => $order->getCodOrden()]);
                        $res->addMsg("Ya has creado la orden con anterioridad: <a href=\"$url\">Descargala acá<a/>.");
                    } else {
                        $res->success();
                    }
                }
            } else {
                $res->addMsg('No eres de primer año');
                $res->addMsg($result->getMsg());
            }
        } else {
            //INSCRIPTION ALREADY MADE
            $res->addMsg('Ya estás inscrito');
        }
        return $res;
    }

    public function viewAction() {
        $result = $this->canCreateInscription();
        if ($result->get() == false) {
            $msg = new Message('No Puedes Generar La Orden', $result);
        } else {
            $enable = true;
        }
        $this->pg()->log(null, LM::SUCCESS, LM::VIEW);
        return $this->getInscriptionView($msg ?? null, $enable ?? false);
    }

    public function generateOrderAction() {
        $result = $this->canCreateInscription();
        if ($result->get() == false) {
            $msg = new Message('No Puedes Generar La Orden', $result);
            $this->pg()->log($result, LM::FAILURE, LM::CREATE);
        } else {
            //CREATING ORDER
            $result = $this->orderManager->createInscriptionOrder($this->identity());
            if ($result->get() == false) {
                $msg = new Message('Orden No Creada', $result);
                $enable = true;
            } else {
                $order = $result->getObj();
                $url = $this->url()->fromRoute('order', ['id' => $order->getCodOrden()]);
                $msg = new Message('Orden Creada', "Puedes descargar tu orden <a href=\"$url\"> en este link </a>, o en la sección de Órdenes de Pago.", Message::GREEN);
            }
            $this->pg()->log($result, $result->get() ? LM::SUCCESS : LM::ERROR, LM::CREATE);
        }
        return $this->getInscriptionView($msg ?? null, $enable ?? false);
    }

}
