<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.6                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2015                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2015
 * $Id$
 *
 */
class CRM_Contact_Form_Search_Custom_ActiveDonors extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {

  protected $_formValues;
  protected $_aclFrom = NULL;
  protected $_aclWhere = NULL;
  public $_permissionedComponent;

  /**
   * @param $formValues
   */
  public function __construct(&$formValues) {
    $this->_formValues = $formValues;

    // Define the columns for search result rows
    $this->_columns = array(
      ts('Contact ID')         => 'contact_id',
      ts('Name')               => 'sort_name',
      ts('Amount')             => 'amount',
      ts('Type')               => 'type',
      ts('Received Date')      => 'receive_date',
      ts('Start Date')         => 'start_date',
      ts('End Date')           => 'end_date',
      ts('Frequency')           => 'frequency_unit',
      ts('Installments')       => 'installments',
    );

    // define component access permission needed
    $this->_permissionedComponent = 'CiviContribute';
  }

  /**
   * @param CRM_Core_Form $form
   */
  public function buildForm(&$form) {

    /**
     * You can define a custom title for the search form
     */
    $this->setTitle('Find Recurring Active Donors');

    //Adding date range field
    $date_range = array('this.year'    => 'This Year',
                        'this.month'   => 'This Month',
                        'this.week'    => 'This Week',
                        'this.day'     => 'Today',
                        'previous.day' => 'Yesterday',
                        'ending.year'  => 'Last Year',
                        'ending.month' => 'Last Month'
                       );

    $form->add('select', 'contribution_recur_end_date', ts('Date Range'), array('' => ts('- select range-')) + $date_range, FALSE, array('class' => 'crm-select2 huge'));

    /**
     * If you are using the sample template, this array tells the template fields to render
     * for the search form.
     */
    $form->assign('elements', array('contribution_recur_end_date'));
  }

  /**
   * Define the smarty template used to layout the search form and results listings.
   *
   * @return string
   */
  public function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }

  /**
   * Construct the search query.
   *
   * @param int $offset
   * @param int $rowcount
   * @param string|object $sort
   * @param bool $includeContactIDs
   * @param bool $justIDs
   *
   * @return string
   */
  public function all(
    $offset = 0, $rowcount = 0, $sort = NULL,
    $includeContactIDs = FALSE, $justIDs = FALSE
  ) {

    // SELECT clause must include contact_id as an alias for civicrm_contact.id
    if ($justIDs) {
      $select = "contact_a.id as contact_id";
    }
    else {
      $select = "
DISTINCT contact_a.id as contact_id,
contact_a.sort_name as sort_name,
contrib_rec.id as id,
CONCAT ('Every ', contrib_rec.frequency_interval,' ', contrib_rec.frequency_unit, '(s)') as frequency_unit,
contrib_rec.frequency_interval as frequency_interval,
contrib_rec.installments as installments,
contrib_rec.amount as amount,
contrib_rec.start_date as start_date,
contrib_rec.end_date as end_date,
contrib.receive_date as receive_date,
financial_type.name as type
";
    }
    $from = $this->from();

    $where = $this->where($includeContactIDs);

    $having = $this->having();
    if ($having) {
      $having = " HAVING $having ";
    }

    $sql = "
SELECT $select
FROM   $from
WHERE  $where
$having
";
    //GROUP BY contact_a.id

    //for only contact ids ignore order.
    if (!$justIDs) {
      // Define ORDER BY for query in $sort, with default value
      if (!empty($sort)) {
        if (is_string($sort)) {
          $sort = CRM_Utils_Type::escape($sort, 'String');
          $sql .= " ORDER BY $sort ";
        }
        else {
          $sql .= " ORDER BY " . trim($sort->orderBy());
        }
      }
      else {
        $sql .= "ORDER BY contrib_rec.frequency_unit desc";
      }
    }

    if ($rowcount > 0 && $offset >= 0) {
      $offset = CRM_Utils_Type::escape($offset, 'Int');
      $rowcount = CRM_Utils_Type::escape($rowcount, 'Int');
      $sql .= " LIMIT $offset, $rowcount ";
    }

    return $sql;
  }

  /**
   * @return string
   */
  public function from() {
    $this->buildACLClause('contact_a');
    $from = "
            civicrm_contribution_recur AS contrib_rec
            LEFT JOIN civicrm_contribution contrib ON (contrib_rec.id = contrib.contribution_recur_id)
            LEFT JOIN civicrm_contact AS contact_a ON (contact_a.id = contrib_rec.contact_id)
            LEFT JOIN civicrm_financial_type AS financial_type ON (financial_type.id = contrib_rec.financial_type_id)
            {$this->_aclFrom}
          ";

    return $from;
  }

  /**
   * WHERE clause is an array built from any required JOINS plus conditional filters based on search criteria field values.
   *
   * @param bool $includeContactIDs
   *
   * @return string
   */
  public function where($includeContactIDs = FALSE) {
    $clauses = array();

    $clauses[] = "contrib_rec.contact_id = contact_a.id";
    $clauses[] = "contrib_rec.is_test = 0";
    $date_range = isset($this->_formValues['contribution_recur_end_date']) ? $this->_formValues['contribution_recur_end_date'] : NULL;

    //Building date range where clause
    if($date_range) {
      $date_where = $this->datewhereClause($date_range);
      if($date_where) {
        $clauses[] = $date_where;
      }
    }

    return implode(' AND ', $clauses);
  }

  /**
   * @param bool $includeContactIDs
   *
   * @return string
   */
  public function having($includeContactIDs = FALSE) {
    $clauses = array();
    return implode(' AND ', $clauses);
  }

  /*
   * Functions below generally don't need to be modified
   */

  /**
   * @inheritDoc
   */
  public function count() {
    $sql = $this->all();

    $dao = CRM_Core_DAO::executeQuery($sql,
      CRM_Core_DAO::$_nullArray
    );
    return $dao->N;
  }

  /**
   * @param int $offset
   * @param int $rowcount
   * @param null $sort
   * @param bool $returnSQL Not used; included for consistency with parent; SQL is always returned
   *
   * @return string
   */
  public function contactIDs($offset = 0, $rowcount = 0, $sort = NULL, $returnSQL = TRUE) {
    return $this->all($offset, $rowcount, $sort, FALSE, TRUE);
  }

  /**
   * @return array
   */
  public function &columns() {
    return $this->_columns;
  }

  /**
   * @param $title
   */
  public function setTitle($title) {
    if ($title) {
      CRM_Utils_System::setTitle($title);
    }
    else {
      CRM_Utils_System::setTitle(ts('Search'));
    }
  }

  /**
   * @return null
   */
  public function summary() {
    return NULL;
  }

  /**
   * @param string $tableAlias
   */
  public function buildACLClause($tableAlias = 'contact') {
    list($this->_aclFrom, $this->_aclWhere) = CRM_Contact_BAO_Contact_Permission::cacheClause($tableAlias);
  }

  /**
   * Helper function to generate date range where clasue
   */

  public function datewhereClause($date_range) {

    $date   = explode(".", $date_range);
    $state  = $date[0];
    $filter = $date[1];
    $date_where = "";
    switch($state) {

      case "this" :
        $end_date = "NOW()";
        if($filter == 'month') {
          $start_date = "DATE_FORMAT(NOW() ,'%Y-%m-01')";
        }elseif($filter == 'year') {
          $start_date = "DATE_FORMAT(NOW() ,'%Y-01-01')";
        }elseif($filter == 'week') {
          $start_date = "DATE_SUB(NOW(), INTERVAL DAYOFWEEK(NOW())-1 DAY)";
        }else{
          $start_date = "like concat(CURDATE(),'%')";
          $day = 'today';
        }
        break;
      case "previous" :
       $start_date = "like concat(DATE_SUB(NOW(), INTERVAL 1 {$filter}), '%')";
       $day = 'yesterday';
        break;
      case "ending" :
        if($filter == 'month') {
          $start_date = "DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01')";
          $end_date = "DATE_SUB(DATE_ADD(DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01'), INTERVAL 1 MONTH), INTERVAL 1 DAY)";
        }else {
          $end_date = "DATE_SUB(DATE_ADD(DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 YEAR), '%Y-%01-01'), INTERVAL 1 YEAR), INTERVAL 1 DAY)";
          $start_date = "DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 YEAR), '%Y-01-01')";
        }

        break;
    }
    if(isset($day) && ($day == 'today' || $day == 'yesterday')) {
      $date_where = "(contrib.receive_date $start_date)";
    }elseif($start_date && $end_date) {
      $date_where = "(contrib.receive_date <= {$end_date} AND contrib.receive_date >= {$start_date})";
    }
    return $date_where;
  }
}
