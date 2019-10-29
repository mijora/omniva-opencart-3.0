<?php
/**
 * Omnivalt extension general controller
 * for settings enable/disable/install module
 * @version 1.1.0 Email/OOP
 * @author mijora.lt
 */
class ControllerExtensionShippingOmnivalt extends Controller
{
    private $error = array();

    public function install()
    {
        $sql = "ALTER TABLE " . DB_PREFIX . "order ADD `labelsCount` INT NOT NULL DEFAULT '1',
                                              ADD `omnivaWeight` FLOAT NOT NULL DEFAULT '1',
                                              ADD `cod_amount` FLOAT DEFAULT 0;";
        $this->db->query($sql);
        $this->load->model('setting/setting');
        $sql2 = "CREATE TABLE " . DB_PREFIX . "order_omniva (id int NOT NULL AUTO_INCREMENT, tracking TEXT, manifest int, labels text, id_order int, PRIMARY KEY (id), UNIQUE (id_order));";
        $this->model_setting_setting->editSetting('omniva', array('omniva_manifest' => 0));
        $this->db->query($sql2);
        //$this->model_setting_setting->editSetting('shipping_omniva', array('shipping_omnivalt_terminals_LT' => null));
    }

    public function uninstall()
    {
        $sql = "ALTER TABLE " . DB_PREFIX . "order DROP COLUMN labelsCount,
                                        DROP COLUMN omnivaWeight,
                                        DROP COLUMN cod_amount; ";

        $this->db->query($sql);
        $sql2 = "DROP TABLE " . DB_PREFIX . "order_omniva";
        $this->db->query($sql2);

    }

    public function index()
    {
        $this->load->language('extension/shipping/omnivalt');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('shipping_omnivalt', $this->request->post);
            if (isset($this->request->post['shipping_omnivalt_enable_templates']) && $this->request->post['shipping_omnivalt_enable_templates'] != true) {
                $this->model_setting_setting->editSetting('shipping_omnivalt_enable_templates', 0);
            }

            if (!empty($this->request->post['download'])) {
                $this->fetchUpdates();
            } else {
                $this->response->redirect($this->url->link('marketplace/extension', 'type=shipping&user_token=' . $this->session->data['user_token'], 'SSL'));
            }
        }

        foreach (array('cron_url', 'heading_title', 'text_edit', 'text_enabled', 'text_disabled', 'text_yes', 'text_no', 'text_none', 'text_parcel_terminal', 'text_courier', 'text_sorting_center', 'entry_url', 'entry_user', 'entry_password', 'entry_service', 'entry_pickup_type', 'entry_company', 'entry_bankaccount', 'entry_pickupstart', 'entry_pickupfinish', 'entry_cod', 'entry_status', 'entry_sort_order', 'entry_parcel_terminal_price', 'entry_courier_price', 'entry_terminals', 'button_save', 'button_cancel', 'button_download', 'entry_sender_name', 'entry_sender_address', 'entry_sender_city', 'entry_sender_postcode', 'entry_sender_phone', 'entry_sender_country_code') as $key) {
            $data[$key] = $this->language->get($key);
        }

        foreach (array('warning', 'url', 'user', 'password') as $key) {
            if (isset($this->error[$key])) {
                $data['error_' . $key] = $this->error[$key];
            } else {
                $data['error_' . $key] = '';
            }
        }
        $sender_array = array('sender_name', 'sender_address', 'sender_phone',
            'sender_postcode', 'sender_city', 'sender_country_code',
            'sender_phone', 'parcel_terminal_price', 'parcel_terminal_pricelv', 'parcel_terminal_priceee',
            'courier_price', 'courier_pricelv', 'courier_priceee',
        );
        foreach ($sender_array as $key) {
            if (isset($this->error[$key])) {
                $data['error_' . $key] = $this->error[$key];
            } else {
                $data['error_' . $key] = '';
            }
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href'      => $this->url->link('marketplace/extension', 'type=shipping&user_token=' . $this->session->data['user_token'], 'SSL')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/shipping/omnivalt', 'user_token=' . $this->session->data['user_token'], true),
        );

        $data['action'] = $this->url->link('extension/shipping/omnivalt', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'type=shipping&user_token=' . $this->session->data['user_token'], 'SSL');

        foreach ($sender_array as $key) {
            if (isset($this->request->post['shipping_omnivalt_' . $key])) {
                $data['shipping_omnivalt_' . $key] = $this->request->post['shipping_omnivalt_' . $key];
            } else {
                $data['shipping_omnivalt_' . $key] = $this->config->get('shipping_omnivalt_' . $key);
            }
        }

        if (isset($this->request->post['shipping_omnivalt_url'])) {
            $data['shipping_omnivalt_url'] = $this->request->post['shipping_omnivalt_url'];
        } else {
            $data['shipping_omnivalt_url'] = $this->config->get('shipping_omnivalt_url');
        }
        if ($data['shipping_omnivalt_url'] == '') {
            $data['shipping_omnivalt_url'] = 'https://edixml.post.ee';
        }

        if (isset($this->request->post['shipping_omnivalt_user'])) {
            $data['shipping_omnivalt_user'] = $this->request->post['shipping_omnivalt_user'];
        } else {
            $data['shipping_omnivalt_user'] = $this->config->get('shipping_omnivalt_user');
        }

        if (isset($this->request->post['shipping_omnivalt_password'])) {
            $data['shipping_omnivalt_password'] = $this->request->post['shipping_omnivalt_password'];
        } else {
            $data['shipping_omnivalt_password'] = $this->config->get('shipping_omnivalt_password');
        }

        if (isset($this->request->post['shipping_omnivalt_service'])) {
            $data['shipping_omnivalt_service'] = $this->request->post['shipping_omnivalt_service'];
        } elseif ($this->config->has('shipping_omnivalt_service')) {
            $data['shipping_omnivalt_service'] = $this->config->get('shipping_omnivalt_service');
        } else {
            $data['shipping_omnivalt_service'] = array();
        }

        $data['services'] = array();

        $data['services'][] = array(
            'text' => $this->language->get('text_courier'),
            'value' => 'courier',
        );

        $data['services'][] = array(
            'text' => $this->language->get('text_parcel_terminal'),
            'value' => 'parcel_terminal',
        );

        if (isset($this->request->post['shipping_omnivalt_parcel_terminal_price'])) {
            $data['shipping_omnivalt_parcel_terminal_price'] = $this->request->post['shipping_omnivalt_parcel_terminal_price'];
        } else {
            $data['shipping_omnivalt_parcel_terminal_price'] = $this->config->get('shipping_omnivalt_parcel_terminal_price');
        }
        if (isset($this->request->post['shipping_omnivalt_courier_price'])) {
            $data['shipping_omnivalt_courier_price'] = $this->request->post['shipping_omnivalt_courier_price'];
        } else {
            $data['shipping_omnivalt_courier_price'] = $this->config->get('shipping_omnivalt_courier_price');
        }
        //Additions for Latvia
        if (isset($this->request->post['shipping_omnivalt_parcel_terminal_pricelv'])) {
            $data['shipping_omnivalt_parcel_terminal_pricelv'] = $this->request->post['shipping_omnivalt_parcel_terminal_pricelv'];
        } else {
            $data['shipping_omnivalt_parcel_terminal_pricelv'] = $this->config->get('shipping_omnivalt_parcel_terminal_pricelv');
        }
        if (isset($this->request->post['shipping_omnivalt_courier_pricelv'])) {
            $data['shipping_omnivalt_courier_pricelv'] = $this->request->post['shipping_omnivalt_courier_pricelv'];
        } else {
            $data['shipping_omnivalt_courier_pricelv'] = $this->config->get('shipping_omnivalt_courier_pricelv');
        }
        //Additions for Estonia
        if (isset($this->request->post['shipping_omnivalt_parcel_terminal_priceee'])) {
            $data['shipping_omnivalt_parcel_terminal_priceee'] = $this->request->post['shipping_omnivalt_parcel_terminal_priceee'];
        } else {
            $data['shipping_omnivalt_parcel_terminal_priceee'] = $this->config->get('shipping_omnivalt_parcel_terminal_priceee');
        }
        if (isset($this->request->post['shipping_omnivalt_courier_priceee'])) {
            $data['shipping_omnivalt_courier_priceee'] = $this->request->post['shipping_omnivalt_courier_priceee'];
        } else {
            $data['shipping_omnivalt_courier_priceee'] = $this->config->get('shipping_omnivalt_courier_priceee');
        }

        if (isset($this->request->post['shipping_omnivalt_company'])) {
            $data['shipping_omnivalt_company'] = $this->request->post['shipping_omnivalt_company'];
        } else {
            $data['shipping_omnivalt_company'] = $this->config->get('shipping_omnivalt_company');
        }

        if (isset($this->request->post['shipping_omnivalt_bankaccount'])) {
            $data['shipping_omnivalt_bankaccount'] = $this->request->post['shipping_omnivalt_bankaccount'];
        } else {
            $data['shipping_omnivalt_bankaccount'] = $this->config->get('shipping_omnivalt_bankaccount');
        }

        if (isset($this->request->post['shipping_omnivalt_pickupstart'])) {
            $data['shipping_omnivalt_pickupstart'] = $this->request->post['shipping_omnivalt_pickupstart'];
        } else {
            $data['shipping_omnivalt_pickupstart'] = $this->config->get('shipping_omnivalt_pickupstart');
        }
        if ($data['shipping_omnivalt_pickupstart'] == '') {
            $data['shipping_omnivalt_pickupstart'] = "8:00";
        }

        if (isset($this->request->post['shipping_omnivalt_pickupfinish'])) {
            $data['shipping_omnivalt_pickupfinish'] = $this->request->post['shipping_omnivalt_pickupfinish'];
        } else {
            $data['shipping_omnivalt_pickupfinish'] = $this->config->get('shipping_omnivalt_pickupfinish');
        }
        if ($data['shipping_omnivalt_pickupfinish'] == '') {
            $data['shipping_omnivalt_pickupfinish'] = "17:00";
        }

        if (isset($this->request->post['shipping_omnivalt_cod'])) {
            $data['shipping_omnivalt_cod'] = $this->request->post['shipping_omnivalt_cod'];
        } else {
            $data['shipping_omnivalt_cod'] = $this->config->get('shipping_omnivalt_cod');
        }

        if (isset($this->request->post['shipping_omnivalt_pickup_type'])) {
            $data['shipping_omnivalt_pickup_type'] = $this->request->post['shipping_omnivalt_pickup_type'];
        } else {
            $data['shipping_omnivalt_pickup_type'] = $this->config->get('shipping_omnivalt_pickup_type');
        }

        if (isset($this->request->post['shipping_omnivalt_status'])) {
            $data['shipping_omnivalt_status'] = $this->request->post['shipping_omnivalt_status'];
        } else {
            $data['shipping_omnivalt_status'] = $this->config->get('shipping_omnivalt_status');
        }

        if (isset($this->request->post['shipping_omnivalt_sort_order'])) {
            $data['shipping_omnivalt_sort_order'] = $this->request->post['shipping_omnivalt_sort_order'];
        } else {
            $data['shipping_omnivalt_sort_order'] = $this->config->get('shipping_omnivalt_sort_order');
        }
        $data['shipping_omnivalt_terminals'] = $this->loadTerminals();
        if(isset($data['shipping_omnivalt_terminals'])) 
            $data['terminal_count'] = count($data['shipping_omnivalt_terminals']);
         else
            $data['terminal_count'] = 1;
            // foreach($this->model_setting_setting->getSetting('shipping_omnivalt_terminals_LT') as $key => $value) {
            //     echo $key;
            // }
        if (isset($this->request->post['shipping_omnivalt_email_template'])) {
            $data['shipping_omnivalt_email_template'] = $this->request->post['shipping_omnivalt_email_template'];
        } else {
            $data['shipping_omnivalt_email_template'] = $this->config->get('shipping_omnivalt_email_template');
        }
        if (isset($this->request->post['shipping_omnivalt_enable_templates'])) {
            $data['shipping_omnivalt_enable_templates'] = $this->request->post['shipping_omnivalt_enable_templates'];
        } else {
            $data['shipping_omnivalt_enable_templates'] = $this->config->get('shipping_omnivalt_enable_templates');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/shipping/omnivalt', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/shipping/omnivalt')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['shipping_omnivalt_url']) {
            $this->error['url'] = $this->language->get('error_url');
        }

        if (!$this->request->post['shipping_omnivalt_user']) {
            $this->error['user'] = $this->language->get('error_user');
        }

        if (!$this->request->post['shipping_omnivalt_password']) {
            $this->error['password'] = $this->language->get('error_password');
        }

        foreach (array('sender_name', 'sender_address', 'sender_phone', 'sender_postcode', 'sender_city', 'sender_country_code', 'sender_phone', 'parcel_terminal_price', 'parcel_terminal_pricelv', 'parcel_terminal_priceee', 'courier_price', 'courier_pricelv', 'courier_priceee') as $key) {
            if (!$this->request->post['shipping_omnivalt_' . $key]) {
                $this->error[$key] = $this->language->get('error_required');
            }
        }
        return !$this->error;
    }

    private function loadTerminals()
    {
        $terminals_json_file_dir = DIR_DOWNLOAD."omniva_terminals.json";
        if (!file_exists($terminals_json_file_dir))
        return false;
        $terminals_file = fopen($terminals_json_file_dir, "r");
        if (!$terminals_file)
        return false;
        $terminals = fread($terminals_file, filesize($terminals_json_file_dir) + 10);
        fclose($terminals_file);
        $terminals = json_decode($terminals, true);
        return $terminals;
    }

    private function fetchUpdates()
    {
        $terminals = array();
        $csv = $this->fetchURL('https://www.omniva.ee/locations.csv');
        if (empty($csv)) {
            return;
        }
        $countries = array();
        $countries['LT'] = 1;
        $countries['LV'] = 2;
        $countries['EE'] = 3;
        $cabins = $this->parseCSV($csv, $countries);
        if ($cabins) {
            $terminals = $cabins;
            $fp = fopen(DIR_DOWNLOAD."omniva_terminals.json", "w");
            fwrite($fp, json_encode($terminals));
            fclose($fp);
        }
        $this->csvTerminal();
    }
    private function fetchURL($url)
    {
        $ch = curl_init(trim($url)) or die('cant create curl');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $out = curl_exec($ch) or die(curl_error($ch));
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            die('cannot fetch update from ' . curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) . ': ' . curl_getinfo($ch, CURLINFO_HTTP_CODE));
        }

        curl_close($ch);
        return $out;
    }

    public function csvTerminal() {
        
        $url = 'https://www.omniva.ee/locations.json';
        $fp = fopen(DIR_DOWNLOAD."locations.json", "w");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_FILE, $fp);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($curl);
        curl_close($curl);
        fclose($fp);
    }

    private function parseCSV($csv, $countries = array())
    {
        $cabins = array();
        if (empty($csv)) {
            return $cabins;
        }
        if (mb_detect_encoding($csv, 'UTF-8, ISO-8859-1') == 'ISO-8859-1') {
            $csv = utf8_encode($csv);
        }
        $rows = str_getcsv($csv, "\n"); #parse the rows, remove first
        $newformat = count(str_getcsv($rows[0], ';')) > 10 ? 1 : 0;
        array_shift($rows);
        foreach ($rows as $row) {
            $cabin = str_getcsv($row, ';');
            # there are lines with all fields empty in estonian file, workaround
            if (count(array_filter($cabin))) {
                if ($newformat) {
                    if (!empty($countries[strtoupper(trim($cabin[3]))])) {
                        # closed ? exists on EE only
                        if (intval($cabin[2])) {
                            continue;
                        }
                        $cabin = array($cabin[1], $cabin[4], trim($cabin[5] . ' ' . ($cabin[8] != 'NULL' ? $cabin[8] : '') . ' ' . ($cabin[10] != 'NULL' ? $cabin[10] : '')), $cabin[0], $cabin[20], $cabin[3]);
                    } else {
                        $cabin = array();
                    }
                }
                if ($cabin) {
                    $cabins[] = $cabin;
                }
            }
        }
        return $cabins;
    }
}
