<?php
/**
 * Created by PhpStorm.
 * User: attilaolbrich
 * Date: 16/11/2017
 * Time: 09:18
 */

class Myshipserv_Spr_ExportConverter
{

    /**
     * This function converts data to the desired format
     *
     * @param array $data
     * @param string $type
     * @return array
     */
    public static function convert(array $data, $type)
    {
        $roundings = null;

        switch ($type) {
            case 'common-items':
                $conversionArray = array(
                    'part-no' => 'Part No.',
                    'description' => 'Description',
                    'uom' => 'Unit of measure',
                    'quantity' => 'Quantity purchased',
                    'average-unit-price' => 'Average unit price (USD)',
                    'total-spend' => 'Total spend (USD)'
                );

                $roundings = array(
                    'total-spend' => 2
                );

                break;
            case 'spend-by-vessel-items':
                $conversionArray = array(
                    'vessel-imo-no' => 'IMO',
                    'vessel-name' => 'Vessel name',
                    'vessel-type-name' => 'Vessel type',
                    'ord-count' => 'Orders',
                    'ord-total-cost-discounted-usd' => 'Spend (USD)'
                );

                $roundings = array(
                    'ord-total-cost-discounted-usd' => 0
                );

                break;
            case 'spend-by-purchaser-items':
                $conversionArray = array(
                    'name-and-email' => 'Name / email address',
                    'rfq-count-total' => 'RFQs',
                    'ord-count-competitive' => 'Competitive orders',
                    'ord-count-direct' => 'Direct orders',
                    'rfq-count-no-order' => 'RFQs no PO'
                );
                break;
            default:
                //No conversion if type is not known
                return $data;
                break;
        }

        $result = array();

        foreach ($data as $record) {
            $tmpRecord = array();
            foreach ($conversionArray as $key => $value) {
                if (is_array($roundings) && array_key_exists($key, $roundings)) {
                    $tmpRecord[$value] = number_format(round((float)$record[$key], (int)$roundings[$key]), (int)$roundings[$key], '.', '');;
                } else {
                    $tmpRecord[$value] = $record[$key];
                }

            }
            array_push($result, $tmpRecord);
        }

        return $result;

    }

}