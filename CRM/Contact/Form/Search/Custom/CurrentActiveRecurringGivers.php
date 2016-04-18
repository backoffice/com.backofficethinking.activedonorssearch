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
class CRM_Contact_Form_Search_Custom_CurrentActiveRecurringGivers extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {

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
      ts('Civi ID')           => 'contact_id',
      ts('First Name')        => 'first_name',
      ts('Last Name')         => 'last_name',
      ts('Email Address')     => 'email',
      ts('Phone Number')      => 'phone',
      ts('Street Address')    => 'street_address',
      ts('City')              => 'city',
      ts('State')             => 'abbreviation',
      ts('Zip Code')          => 'postal_code',
      ts('Financial Type')    => 'fin_type',
      ts('Giving Frequency')  => 'giving_frequency',
      ts('First Gift Date')   => 'first_gift_date',
      ts('First Gift Amount') => 'first_gift_amount',
      ts('Recent Gift Date')  => 'most_recent_gift_date',
      ts('Most Recent Gift')  => 'most_recent_gift_amount',
      ts('Total Donations')   => 'total_donations',
      ts('Total Recurring')   => 'total_recurring_donations'
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
    $this->setTitle('Find Current Active Recurring Givers');

    //Adding frequency unit field
    $frequency_unit = array('month'   => 'Month',
                            'quarter' => 'Quarter',
                            'year'    => 'Year',
                           );

    $form->add('select', 'frequency_unit', ts('Frequency Unit'), $frequency_unit, FALSE, array('class' => 'crm-select2 huge', 'multiple' => 'multiple', 'placeholder' => '-any-'));

    $form->addSelect('financial_type_id',
      array('label'=> 'Financial Type', 'entity' => 'contribution', 'multiple' => 'multiple', 'context' => 'search', 'class' => 'crm-select2 huge')
    );

    /**
     * If you are using the sample template, this array tells the template fields to render
     * for the search form.
     */
    $form->assign('elements', array('frequency_unit', 'financial_type_id'));
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

    if ($justIDs) {
      $select = "contact_a.id as contact_id";
    }
    else {
      $select = "
DISTINCT contact_a.id as contact_id,
contact_a.first_name as first_name, contact_a.last_name as last_name, e.email as email, ph.phone as phone, 
a.street_address as street_address, a.city as city, sp.abbreviation as abbreviation, a.postal_code as postal_code,
DATE_FORMAT(firstgift.receive_date, '%M %D, %Y %l:%i %p') as 'first_gift_date', CONCAT('$ ',firstgift.total_amount) as 'first_gift_amount',
DATE_FORMAT(mostrecent.receive_date, '%M %D, %Y %l:%i %p') as 'most_recent_gift_date',
CONCAT('$ ',mostrecent.total_amount) as 'most_recent_gift_amount',
cr.id, financial_type.name as fin_type, CONCAT('$ ',ifnull(contribution_all.total_donations,0)) as 'total_donations',
CONCAT('$ ',ifnull(contribution_all_recur.total_donations,0)) as 'total_recurring_donations',
case when cr.frequency_unit='month' and cr.frequency_interval = 1 then 'Every 1 Month'
     when cr.frequency_unit='month' and cr.frequency_interval = 3 then 'Every 3 Months'
     when cr.frequency_unit='month' and cr.frequency_interval = 2 then 'Every 2 Months'
     when cr.frequency_unit='year' and cr.frequency_interval = 1 then 'Every 1 Year'
     when cr.frequency_unit='quarter' and cr.frequency_interval = 1 then 'Every 1 Quarter'
end as 'giving_frequency'";
    }
    $from = $this->from();

    $where = $this->where($includeContactIDs);

    $sql = "
SELECT $select
FROM   $from
WHERE  $where
GROUP BY contact_a.id, cr.frequency_interval
";

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
        $sql .= "ORDER BY cb.receive_date desc";
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
civicrm_contact as contact_a
LEFT OUTER JOIN civicrm_address a on a.contact_id = contact_a.id and a.is_primary = 1
LEFT OUTER JOIN civicrm_state_province sp on sp.id = a.state_province_id and sp.country_id = a.country_id
LEFT OUTER JOIN civicrm_phone ph on ph.contact_id = contact_a.id and ph.is_primary = 1
LEFT OUTER JOIN civicrm_email e on e.contact_id = contact_a.id and e.is_primary = 1
INNER JOIN civicrm_contribution cb on cb.contact_id = contact_a.id
INNER JOIN civicrm_financial_type financial_type on (financial_type.id = cb.financial_type_id)
INNER JOIN civicrm_contribution_recur cr on cr.id = cb.contribution_recur_id
LEFT OUTER JOIN civicrm_contribution mostrecent on mostrecent.id = (select max(id) from civicrm_contribution where contribution_status_id = 1 and contribution_recur_id = cr.id and contact_id = contact_a.id)
LEFT OUTER JOIN civicrm_contribution firstgift on firstgift.id = (select id from civicrm_contribution where contribution_status_id = 1 and contribution_recur_id = cr.id  and contact_id = contact_a.id order by receive_date limit 1)
   LEFT OUTER JOIN
    (SELECT contact_id, sum(total_amount) as total_donations, contribution_recur_id
        FROM civicrm_contribution
            WHERE contribution_status_id = 1
            GROUP BY contribution_recur_id) as contribution_all_recur on cr.id = contribution_all_recur.contribution_recur_id
   LEFT OUTER JOIN
    (SELECT contact_id, sum(total_amount) as total_donations, contribution_recur_id
        FROM civicrm_contribution
            WHERE contribution_status_id = 1
            GROUP BY contact_id) as contribution_all on contact_a.id = contribution_all.contact_id
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

    $clauses[] = "cb.contribution_status_id = 1";
    $clauses[] = "cb.contribution_recur_id is not null";

    //Adding Finanacial type in filter
    if (!empty($this->_formValues['financial_type_id'])) {
      $financial_type_ids = implode(',', array_values($this->_formValues['financial_type_id']));
      $clauses[] = "(cb.financial_type_id IN ($financial_type_ids))";
    }

   //Building frequency unit where clause
    $frequency_unit = $this->_formValues['frequency_unit'];

    if(!empty($frequency_unit)) {
      foreach($frequency_unit as $freq_unit) {
        switch($freq_unit) {
          case 'month' :
            $freq_clause[] = "(case when cr.frequency_unit='month' and cr.frequency_interval=1 then cb.receive_date >= date_sub(curdate(),interval 1 month)
                               when cr.frequency_unit='month' and cr.frequency_interval=3 then cb.receive_date >= date_sub(curdate(),interval 3 month)
                               when cr.frequency_unit='month' and cr.frequency_interval=2 then cb.receive_date >= date_sub(curdate(),interval 2 month)
                               end)";
            break;
          case 'quarter' :
            $freq_clause[] = "(case when cr.frequency_unit='quarter' and cr.frequency_interval=1
                               then cb.receive_date >= date_sub(curdate(),interval 1 QUARTER) end)";
            break;
          case 'year' :
            $freq_clause[] = "(case when cr.frequency_unit='year' and cr.frequency_interval=1
                               then cb.receive_date >= date_sub(curdate(),interval 1 year) end)";
            break;
        }
      }

      $clauses[] = "(".implode(' OR ', array_values($freq_clause)).")";
    }

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

}
