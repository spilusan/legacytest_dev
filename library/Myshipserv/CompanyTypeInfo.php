<?php
/**
 * Class CompanyTypeInfo
 * Return the company info, Text representatio of the company type or abrevation of the company type
 */

class Myshipserv_CompanyTypeInfo
{

    /**
     * Return company type info
     *
     * @param string $type
     * @param bool $fullName
     * @return string
     */
    public static function getName($type, $fullName = false)
    {
        switch ($type) {
            case 'v':
                $ctName = 'Supplier';
                $ctType = 'S';
                break;
            case 'c':
                $ctName = 'Consortia';
                $ctType = 'C';
                break;
            default:
                $ctName = 'Buyer';
                $ctType = 'B';
                break;
        }

        return ($fullName) ? $ctName : $ctType;
    }
}