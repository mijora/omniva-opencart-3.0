<?php
/**
 * Generating omnivalt shipping selector
 * within opencart checkout 3 states available
 * courier and parcel terminals
 */
class ModelExtensionShippingOmnivalt extends Model
{
    public function getQuote($address)
    {
        
        //return array();
        $currency_carrier = "EUR";
        $total_kg = $this->cart->getWeight();
        $weight_class_id = $this->config->get('config_weight_class_id');
        $unit = $this->db->query("SELECT unit FROM " . DB_PREFIX . "weight_class_description wcd WHERE (weight_class_id = " . $weight_class_id . ") AND language_id = '" . (int) $this->config->get('config_language_id') . "'");
        $unit = $unit->row['unit'];
        if ($unit == 'g') {
            $total_kg /= 1000;
        }
        $this->load->language('extension/shipping/omnivalt');

        $method_data = array();
        $service_Actives = $this->config->get('shipping_omnivalt_service');

        if (is_array($service_Actives) && count($service_Actives) && ($address['iso_code_2'] == 'LT' || 
                                                        $address['iso_code_2'] == 'LV' || 
                                                        $address['iso_code_2'] == 'EE')) {
            foreach ($service_Actives as $key => $service_Active) {
                $cabine_select2 = '';
                $first = '';
                $price = $this->config->get('shipping_omnivalt_' . $service_Active . '_price');
                if ($address['iso_code_2'] == 'LV' && $service_Active == "parcel_terminal") {
                    $price = $this->config->get('shipping_omnivalt_parcel_terminal_pricelv');
                }
                if ($address['iso_code_2'] == 'LV' && $service_Active == "courier") {
                    $price = $this->config->get('shipping_omnivalt_courier_pricelv');
                }
                if ($address['iso_code_2'] == 'EE' && $service_Active == "parcel_terminal") {
                    $price = $this->config->get('shipping_omnivalt_parcel_terminal_priceee');
                }
                if ($address['iso_code_2'] == 'EE' && $service_Active == "courier") {
                    $price = $this->config->get('shipping_omnivalt_courier_priceee');
                }

                if (stripos($price, ':') !== false) {
                    $prices = explode(',', $price);
                    if (!is_array($prices)) {
                        continue;
                    }
                    $price = false;
                    foreach ($prices as $price) {
                        $priceArr = explode(':', str_ireplace(array(' ', ','), '', $price));
                        if (!is_array($priceArr) || count($priceArr) != 2) {
                            continue;
                        }
                        if ($priceArr[0] >= $total_kg) {
                            $price = $priceArr[1];
                            break;
                        }
                    }
                }
                if ($price === false) {
                    continue;
                }
                
                $title = $this->language->get('text_' . $service_Active);
                if ($service_Active == "parcel_terminal" && $cabins = $this->config->get('omnivalt_terminals_LT')) {
                    
                    $cabine_select2 = '<script>$( "input[name=shipping_method]" ).focus(function() { $( this ).blur(); });
                    $(".omniva_terminal_opt").parent().parent().hide();
                    </script>
                    <select name="omnivalt_parcel_terminal" id="omnivalt_parcel_terminal"  class="form-control form-inline input-sm" style="width: 70%; display: inline;"
                    onchange="$(\'#omnivalt_parcel_terminal\').parent().find(\'input\').eq(0).val($(this).val()); $(\'#omnivalt_parcel_terminal\').parent().find(\'input\').eq(0).prop(\'checked\',true);"
                    onfocus="$(\'#omnivalt_parcel_terminal\').parent().find(\'input\').eq(0).prop(\'checked\',true);">';

                    usort($cabins, function ($a, $b) {if ($a[1] == $b[1]) {
                        return ($a[0] < $b[0]) ? -1 : 1;
                    }
                        return ($a[1] < $b[1]) ? -1 : 1;});
                    $cabine_select2 .= $this->groupTerminals($cabins, $address['iso_code_2']);
                    $terminalsArr = array();

                    foreach ($cabins as $cabin) {

                        if (isset($cabin[5]) && $cabin[5] != $address['iso_code_2']) 
                            continue;
                        if (!$first) {
                            $first = $cabin[3];
                        }
      
                        $sub_quote2['parcel_terminal_'. $cabin[3]] = array(
                            'code' => 'omnivalt.parcel_terminal_' . $cabin[3],
                            'title' => '<div class="omniva_terminal_opt">'.$title . ': ' . $cabin[0] . ' ' . $cabin[2].'</div>',
                            'cost' =>    $this->currency->convert($price, $currency_carrier, $this->config->get('config_currency')),
                            'tax_class_id' => 0,
                            'sort_order' => $this->config->get('shipping_omnivalt_sort_order'),
                            'text' =>  $this->currency->format($this->currency->convert($price, 
                                                                                        $currency_carrier, 
                                                                                        $this->session->data['currency']), 
                                                                $this->session->data['currency']),
                        );
                  
/*
                        $sub_quote2['parcel_terminal_fake' . $cabin[3]] = array(
                            'code' => 'omnivalt.parcel_terminal_fake' . $cabin[3],
                            'title' => '<div id="parcel_terminal_fake' . $cabin[3] . '"></div>',
                            'cost' => $this->currency->convert($price, $currency_carrier, $this->config->get('config_currency')),
                            'tax_class_id' => 0,
                            'text' => 'fake',
                        );*/
                        
                    }
                    $cabine_select2 .= '</select>';
                }
                
                $codeCarrier = "omnivalt";
                if ($service_Active == "parcel_terminal") {
                    $codeCarrier = 'fake';
                }

                $quote_data2[$service_Active] = array(
                    'code' => $codeCarrier . '.' . $service_Active,
                    'title' => $title . $cabine_select2,
                    'cost' => $this->currency->convert($price, $currency_carrier, $this->config->get('config_currency')),
                    'tax_class_id' => 0,
                    'sort_order' => $this->config->get('shipping_omnivalt_sort_order'),
                    'text' => $this->currency->format(
                                        $this->currency->convert(
                                                            $price, 
                                                            $currency_carrier, 
                                                            $this->session->data['currency']), 
                                                            $this->session->data['currency']),
                );
            }
            if (!(isset($sub_quote2)) || !is_array($sub_quote2)) {
                $sub_quote2 = array();
            }

            if (!(isset($quote_data2)) || !is_array($quote_data2)) {
                return '';
            }
            
            $method_data2 = array(
                'code' => 'omnivalt',
                'title' => $this->language->get('text_title'),
                'quote' => array_merge($quote_data2, $sub_quote2),
                'sort_order' => $this->config->get('shipping_omnivalt_sort_order'),
                'error' => '',
            );
        }
        return $method_data2;
    }
    private function groupTerminals($terminals, $country = 'LT', $selected = '')
    {
        $parcel_terminals = '';
        if (is_array($terminals)) {
            $grouped_options = array();
            foreach ($terminals as $terminal) {
                if (isset($terminal[5]) && $terminal[5] == $country) {
                    if (!isset($grouped_options[$terminal[1]])) {
                        $grouped_options[(string) $terminal[1]] = array();
                    }

                    $grouped_options[(string) $terminal[1]][(string) $terminal[3]] = $terminal[0] . ', ' . $terminal[2];
                }
            }
            foreach ($grouped_options as $city => $locs) {
                $parcel_terminals .= '<optgroup label = "' . $city . '">';
                foreach ($locs as $key => $loc) {
                    $parcel_terminals .= '<option value = "omnivalt.parcel_terminal_' . $key . '" ' . ($key == $selected ? 'selected' : '') . '>' . $loc . '</option>';
                }
                $parcel_terminals .= '</optgroup>';
            }
        }
        $parcel_terminals = '<option value = "" selected disabled>' . $this->language->get('text_select_terminal') . '</option>' . $parcel_terminals;
        return $parcel_terminals;
    }

    
}
