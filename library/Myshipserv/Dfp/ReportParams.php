<?php

use Google\AdsApi\AdManager\v201902\DateRangeType;
use Google\AdsApi\AdManager\v201902\ReportService;

/**
 * Class to hold report filter date
 *
 * Class Myshipserv_Dfp_ReportParams
 */
class Myshipserv_Dfp_ReportParams
{

    protected $dimensions;
    protected $dimensionAttributes;
    protected $columns;
    protected $dateRangeType;
	protected $startDate;
	protected $endDate;
    protected $serviceType;

    /**
     * Set default(s)
     *
     * Myshipserv_Dfp_ReportParams constructor.
     */
    public function __construct()
    {
        $this->dateRangeType = DateRangeType::CUSTOM_DATE;
        $this->serviceType = ReportService::class;
    }

    /**
     * Get the dimensions
     *
     * @return array
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }

    /**
     * Get the dimension attributes
     *
     * @return array
     */
    public function getDimensionAttributes()
    {
        return $this->dimensionAttributes;
    }

    /**
     * Get the columns
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Assign list of dimensions
     *
     * @param array $dimensions
     * @return $this;
     */
    public function assignDimensions(array $dimensions)
    {
        $this->dimensions = $dimensions;
        return $this;
    }

    /**
     * Assign list for dimension attributes
     *
     * @param array $dimensionAttributes
     * @return $this;
     */
    public function assignDimensionAttributes(array $dimensionAttributes)
    {
        $this->dimensionAttributes = $dimensionAttributes;
        return $this;
    }

    /**
     * Assign list of columns
     *
     * @param array $columns
     * @return $this
     */
    public function assignColumns(array $columns)
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Add new dimension to the array
     *
     * @param \Google\AdsApi\AdManager\v201902\Dimension $dimension
     * @return $this
     */
    public function addDimensions($dimension)
    {
        $this->dimensions[] = $dimension;
        return $this;
    }

    /**
     * Add new dimension attribute to the dimensions attribute array
     *
     * @param \Google\AdsApi\AdManager\v201902\DimensionAttribute $dimensionAttribute
     * @return $this
     */
    public function addDimensionAttributes($dimensionAttribute)
    {
        $this->dimensionAttributes[] = $dimensionAttribute;
        return $this;
    }

    /**
     * Add new column to the columns array
     *
     * @param \Google\AdsApi\AdManager\v201902\Column $column
     * @return $this
     */
    public function addColumns($column)
    {
        $this->columns[] = $column;
        return $this;
    }

    /**
     * Set date range type
     *
     * @param string $dateRangeType
     * @return $this
     */
    public function setDateRangeType($dateRangeType)
    {
        $this->dateRangeType = $dateRangeType;
        return $this;
    }

    /**
     * Set Start date
     *
     * @param string $startDate
     * @return $this
     */
    public function setStartDate(DateTime $startDate)
    {
        $this->startDate = $startDate;
        return $this;
    }

    /**
     * Set end date
     *
     * @param string $endDate
     * @return $this
     */
    public function setEndDate(DateTime $endDate)
    {
        $this->endDate = $endDate;
        return $this;
    }

    /**
     * Get date range type
     *
     * @return mixed
     */
    public function getDateRangeType()
    {
        return $this->dateRangeType;
    }

    /**
     * Get start date
     *
     * @return mixed
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * Get end date
     *
     * @return mixed
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * Set the Service Type class
     *
     * @param class $serviceType
     * @return $this
     */
    public function setServiceType($serviceType)
    {
        $this->serviceType = $serviceType;
        return $this;
    }

    /**
     * Get the Service Tpe class
     *
     * @return string
     */
    public function getServiceType()
    {
        return $this->serviceType;
    }


}