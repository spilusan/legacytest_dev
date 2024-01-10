<?php
/**
 * Class Shipserv_Company
 *
 * Resolves company by type
 */

class Shipserv_Company
{
    /**
     * @param string $companyType
     * @param integer $companyId
     *
     * @return Shipserv_Buyer|Shipserv_Consortia|Shipserv_Supplier
     *
     * @throws Myshipserv_Exception_MessagedException
     */
    public static function getCompanyByType($companyType, $companyId)
    {
        switch ($companyType) {
            case 'BYO':
                $branch = Shipserv_Buyer::getInstanceById($companyId);
                break;
            case 'BYB':
                $branch = Shipserv_Buyer::getBuyerBranchInstanceById($companyId);
                break;
            case 'SPB':
                $branch = Shipserv_Supplier::getInstanceById($companyId);
                break;
            case 'CON':
                $branch = Shipserv_Consortia::getConsortiaInstanceById($companyId);
                break;
            default:
                throw new Myshipserv_Exception_MessagedException('Company type "' . $companyType . '" does not exists', 500);
        }

        return $branch;
    }

    /**
     * @param string $companyType
     * @param integer $companyId
     *
     * @return string
     *
     * @throws Myshipserv_Exception_MessagedException
     */
    public static function getCompanyNameByType($companyType, $companyId)
    {
        $company = self::getCompanyByType($companyType, $companyId);
        $resolveAdapterArray = array(
            'BYO' => 'name',
            'BYB' => 'bybName',
            'SPB' => 'name',
            'CON' => 'name'
        );

        return $company->$resolveAdapterArray[$companyType];
    }
}
