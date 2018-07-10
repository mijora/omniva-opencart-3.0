<?php
/*
 * Controller for listing omnivalt orders and creating manifest
 *
 * As well saving its history for futher review
 */
class ControllerOmnivaltOmnivalt extends Controller
{
    public function index()
    {

        $this->load->language('extension/shipping/omnivalt');
        $manifest = intval($this->config->get('omniva_manifest'));
        $data['heading_title'] = $this->language->get('heading_title');

        $numRows = $this->db->query("SELECT COUNT(*)
                                        FROM " . DB_PREFIX . "order A
                                        LEFT JOIN " . DB_PREFIX . "order_omniva B ON A.order_id = B.id_order
                                        WHERE order_status_id != 0 AND shipping_code LIKE 'omnivalt%' AND B.tracking IS NOT NULL AND B.manifest != $manifest AND B.manifest != -1
                                        ")->rows;
        $numRows = intval($numRows[0]["COUNT(*)"]);
        $data['countRows'] = $numRows;

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $start = ($page - 1) * 70;
        $limit = 70;

        $pagination = new Pagination();
        $pagination->total = $numRows;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link('omnivalt/omnivalt', 'user_token=' . $this->session->data['user_token'] . '&page={page}#imp', 'SSL');
        $data['pagination'] = $pagination->render();

        $orders = $this->db->query("SELECT order_id, total, date_modified, labelscount, CONCAT(firstname, ' ', lastname) AS full_name, B.tracking, B.manifest, B.labels, B.id_order
                                    FROM " . DB_PREFIX . "order A
                                    LEFT JOIN " . DB_PREFIX . "order_omniva B ON A.order_id = B.id_order
                                    WHERE order_status_id != 0 AND shipping_code LIKE 'omnivalt%' AND B.tracking IS NOT NULL AND B.manifest != $manifest AND B.manifest != -1
                                    ORDER BY manifest DESC, order_id DESC
                                    LIMIT $start, $limit
                                    ;");
        $newOrders = $this->db->query("SELECT order_id, total, date_modified, labelscount, CONCAT(firstname, ' ', lastname) AS full_name, B.tracking, B.manifest, B.labels, B.id_order
                                    FROM " . DB_PREFIX . "order A
                                    LEFT JOIN " . DB_PREFIX . "order_omniva B ON A.order_id = B.id_order
                                    WHERE order_status_id != 0 AND shipping_code LIKE 'omnivalt%' AND (B.tracking IS NULL OR B.manifest = $manifest)
                                    ORDER BY order_id DESC
                                    ;");

        $skipped = $this->db->query("SELECT order_id, total, date_modified, labelscount, CONCAT(firstname, ' ', lastname) AS full_name, B.tracking, B.manifest, B.labels, B.id_order
                    FROM " . DB_PREFIX . "order A
                    LEFT JOIN " . DB_PREFIX . "order_omniva B ON A.order_id = B.id_order
                    WHERE order_status_id != 0 AND shipping_code LIKE 'omnivalt%' AND B.manifest = -1
                    ORDER BY order_id DESC
                    ;");
        $data['newOrders'] = $newOrders->rows;
        $data['newPage'] = $newOrders->rows;
        $data['newPage'] = null;
        $data['skipped'] = $skipped->rows;
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['orders'] = $orders->rows;
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => 'Home', //$this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], 'SSL'),
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_manifest'),
            'href' => $this->url->link('omnivalt/omnivalt', 'user_token=' . $this->session->data['user_token'], true),
        );

        $data['action'] = $this->url->link('omnivalt/omnivalt/newManifest', 'user_token=' . $this->session->data['user_token'], true);
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['skip'] = $this->url->link('omnivalt/omnivalt/skipOrder', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancelSkip'] = $this->url->link('omnivalt/omnivalt/cancelSkip', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);
        $data['client'] = $this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'], true);
        $data['genLabels'] = $this->url->link('omnivalt/omnivaltPrints/labels', 'user_token=' . $this->session->data['user_token'], true);
        $data['labels'] = $this->url->link('omnivalt/omnivaltPrints/printDocs', 'user_token=' . $this->session->data['user_token'], true);
        $data['currentManifest'] = $this->config->get('omniva_manifest');
        $data['newManifest'] = 'Naujas Manifestas';
        $data['token'] = $this->session->data['user_token'];
        $data['search'] = $this->url->link('omnivalt/omnivalt/searchOmnivaOrders', 'user_token=' . $this->session->data['user_token'], true);

        $data['sender'] = $this->config->get('shipping_omnivalt_sender_name');
        $data['phone'] = $this->config->get('shipping_omnivalt_sender_phone');
        $data['postcode'] = $this->config->get('shipping_omnivalt_sender_postcode');
        $data['address'] = $this->config->get('shipping_omnivalt_sender_country_code') . ' ' . $this->config->get('omnivalt_sender_address');

        $this->response->setOutput($this->load->view('omnivalt/omnivalt', $data));
    }

    public function searchOmnivaOrders()
    {
        if (!isset($this->request->post['date_added']) and !isset($this->request->post['customer']) and !isset($this->request->post['tracking_nr'])) {
            return $this->response->setOutput(json_encode(array()));
        }

        $where = '';
        $tracking = $this->request->post['tracking_nr'];
        if ($tracking != '' and $tracking != null and $tracking != 'undefined') {
            $where .= 'AND B.tracking LIKE "%' . $tracking . '%" ';
        }

        $customer = $this->request->post['customer'];
        if ($customer != '' and $customer != null and $customer != 'undefined') {
            $where .= 'AND CONCAT(firstname, " ", lastname) LIKE "%' . $customer . '%" ';
        }

        $date = $this->request->post['date_added'];
        if ($date != null and $date != 'undefined' and $date != '') {
            $where .= 'AND (date_added LIKE "%' . $date . '%" OR date_modified LIKE "%' . $date . '%" )';
        }

        if ($where == '') {
            return $this->response->setOutput(json_encode(array()));
        }

        $orders = $this->db->query("SELECT order_id, total, date_modified, CONCAT(firstname, ' ', lastname) AS full_name, B.tracking, B.manifest, B.labels, B.id_order
        FROM " . DB_PREFIX . "order A
        LEFT JOIN " . DB_PREFIX . "order_omniva B ON A.order_id = B.id_order
        WHERE order_status_id != 0 AND shipping_code LIKE 'omnivalt%' " . $where . "
        ORDER BY manifest DESC, order_id DESC
        ;");

        $i = 0;
        $orderArrBack = array();
        foreach ($orders->rows as $order) {
            $orderArrBack[$i]['order_id'] = $order['order_id'];
            $orderArrBack[$i]['full_name'] = $order['full_name'];
            $tracking = json_decode($order['tracking']);
            if ($tracking != null and is_array($tracking)) {
                $tracking = end($tracking);
            } else {
                $tracking = '';
            }

            $orderArrBack[$i]['tracking'] = $tracking;
            $orderArrBack[$i]['date_modified'] = $order['date_modified'];
            $orderArrBack[$i]['total'] = $order['total'];
            $orderArrBack[$i]['labels'] = $order['labels'];
            $i++;
            if ($i > 50) {
                break;
            }

        }
        return $this->response->setOutput(json_encode($orderArrBack));

    }
    public function skipOrder()
    {
        if (!isset($this->request->get['order_id'])) {
            $this->redirect($this->url->link('omnivalt/omnivalt', 'user_token=' . $this->session->data['user_token'], true));
        }

        $id_order = $this->request->get['order_id'];
        $none = null;
        $manifest = -1;
        $this->db->query("INSERT INTO " . DB_PREFIX . "order_omniva (tracking, manifest, labels, id_order)
            VALUES ('$none','$manifest','$none','$id_order')");

        $this->response->redirect($this->url->link('omnivalt/omnivalt', 'user_token=' . $this->session->data['user_token'], true));

    }

    public function cancelSkip()
    {
        if (!isset($this->request->get['order_id'])) {
            $this->redirect($this->url->link('omnivalt/omnivalt', 'user_token=' . $this->session->data['user_token'], true));
        }

        $id_order = $this->request->get['order_id'];
        $none = null;
        $this->db->query("DELETE FROM " . DB_PREFIX . "order_omniva WHERE id_order=" . $id_order . " AND manifest=-1;");

        $this->response->redirect($this->url->link('omnivalt/omnivalt', 'user_token=' . $this->session->data['user_token'], true));

    }

    public function callCarrier()
    {
        $pickStart = $this->config->get('omnivalt_pickupstart') ? $this->config->get('omnivalt_pickupstart') : '8:00';
        $pickFinish = $this->config->get('omnivalt_pickupfinish') ? $this->config->get('omnivalt_pickupfinish') : '17:00';
        $pickDay = date('Y-m-d');
        if (time() > strtotime($pickDay . ' ' . $pickFinish)) {
            $pickDay = date('Y-m-d', strtotime($pickDay . "+1 days"));
        }

        $xmlRequest = '
      <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://service.core.epmx.application.eestipost.ee/xsd">
         <soapenv:Header/>
         <soapenv:Body>
            <xsd:businessToClientMsgRequest>
               <partner>' . $this->config->get('omnivalt_user') . '</partner>
               <interchange msg_type="info11">
                  <header file_id="' . \Date('YmdHms') . '" sender_cd="' . $this->config->get('omnivalt_user') . '" >
                  <comment>We are ready to pick</comment>
                  </header>
                  <item_list>
                     <item service="QH" >
                        <measures weight="18" />
                        <receiverAddressee >
                            <person_name>' . $this->config->get('omnivalt_sender_name') . '</person_name>
                            <phone>' . $this->config->get('omnivalt_sender_phone') . '</phone>
                            <address postcode="' . $this->config->get('omnivalt_sender_postcode') . '" deliverypoint="' . $this->config->get('omnivalt_sender_city') . '" country="' . $this->config->get('omnivalt_sender_country_code') . '" street="' . $this->config->get('omnivalt_sender_address') . '" />
                        </receiverAddressee>
                        <!--Optional:-->
                        <returnAddressee>
                           <person_name>' . $this->config->get('omnivalt_sender_name') . '</person_name>
                           <!--Optional:-->
                           <phone>' . $this->config->get('omnivalt_sender_phone') . '</phone>
                           <address postcode="' . $this->config->get('omnivalt_sender_postcode') . '" deliverypoint="' . $this->config->get('omnivalt_sender_city') . '" country="' . $this->config->get('omnivalt_sender_country_code') . '" street="' . $this->config->get('omnivalt_sender_address') . '" />
                        </returnAddressee>
                    <onloadAddressee>
                        <person_name>' . $this->config->get('omnivalt_sender_name') . '</person_name>
                        <!--Optional:-->
                        <phone>' . $this->config->get('omnivalt_sender_phone') . '</phone>
                        <address postcode="' . $this->config->get('omnivalt_sender_postcode') . '" deliverypoint="' . $this->config->get('omnivalt_sender_city') . '" country="' . $this->config->get('omnivalt_sender_country_code') . '" street="' . $this->config->get('omnivalt_sender_address') . '" />
                       <pick_up_time start="' . date("c", strtotime($pickDay . ' ' . $pickStart)) . '" finish="' . date("c", strtotime($pickDay . ' ' . $pickFinish)) . '"/>
                     </onloadAddressee>
                     </item>
                  </item_list>
               </interchange>
            </xsd:businessToClientMsgRequest>
         </soapenv:Body>
      </soapenv:Envelope>';
        //$response = $this->load->controller('extension/shipping/omnivalt/api_request', $xmlRequest);
        $response['status'] = true;
        if ($response['status']) {
            return $this->response->setOutput('got_request');
        } else {
            return $this->response->setOutput(json_encode('got_false'));
        }

    }
}
