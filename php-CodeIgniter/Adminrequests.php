<?php
/**
 * @author Dmitriy Lysenko <dicalexon@rambler.ru>, <d.lysenko@smissltd.com>
 */
require_once 'Controller.php';

class AdminRequests extends Controller
{
    const PAGE_SIZE = 10;
    static $order_fields = array(
        'borrower' => '`borrower`',
        'dob' => '`request`.`dob`',
        'amount' => '`request`.`requested_amount`',
        'term' => '`request`.`loan_term`',
        'province' => '`province_name`',
        'net' => '`request`.`net_income`',
        'date' => '`request`.`date`',
        'mode' => '`request`.`mode`',
        'status' => '`request`.`status`',
        'sold' => '`sold_count`'
    );
    
    public function __construct()
    {
        parent::__construct();
        if ($this->CurrentAccountModel->client->role != 'admin')
            redirect(base_url() . 'dashboard/');
    }
    
    private function getFilter()
    {
        $filter = @$_SESSION['requests_filter_admin'];
        if (empty($filter))
            $filter = array();
        if (empty($filter['page']))
            $filter['page'] = 0;
        if (empty($filter['order_field']))
        {
            $filter['order_field'] = 'date';
            $filter['order'] = 'DESC';
        }
        if (empty($filter['order']))
            $filter['order'] = 'ASC';
        if (empty($filter['start_date']))
            $filter['start_date'] = '';
        if (empty($filter['end_date']))
            $filter['end_date'] = '';
        if (empty($filter['show_debug']))
            $filter['show_debug'] = false;
        if (empty($filter['hide_rejected']))
            $filter['hide_rejected'] = false;
        if (empty($filter['hide_succeeded']))
            $filter['hide_succeeded'] = false;
        $field = trim($this->input->get('sort'));
        if (!empty($field) && isset(self::$order_fields[$field]))
            if ($filter['order_field'] == $field)
                $filter['order'] = $filter['order'] == 'ASC' ? 'DESC' : 'ASC';
            else
                $filter['order_field'] = $field;
        if (strlen($this->input->post('clear')) > 0)
        {
            $filter['start_date'] = '';
            $filter['end_date'] = '';
            $filter['show_debug'] = false;
            $filter['hide_rejected'] = false;
            $filter['hide_succeeded'] = false;
        }
        else
        {
            $data = $this->input->post('filter');
            if (!empty($data))
            {
                $start = trim($data['start_date']);
                if (!empty($start) && strtotime($start))
                    $filter['start_date'] = date('Y-m-d', strtotime($start));
                $end = trim($data['end_date']);
                if (!empty($end) && strtotime($end))
                    $filter['end_date'] = date('Y-m-d', strtotime($end));
                $filter['show_debug'] = isset($data['show_debug']) && $data['show_debug'] == 1;
                $filter['hide_rejected'] = isset($data['hide_rejected']) && $data['hide_rejected'] == 1;
                $filter['hide_succeeded'] = isset($data['hide_succeeded']) && $data['hide_succeeded'] == 1;
            }
        }
        return $filter;
    }
    
    private function setFilter($filter)
    {
        $_SESSION['requests_filter_admin'] = $filter;
    }
    
    public function index()
    {
        $page = trim($this->input->get('page'));
        $filter = $this->getFilter();
        $page = strlen($page) > 0 ? intval($page) : $filter['page'];
        $sql = $this->getRequestsSQL($filter);
        $pagination = $this->getRequestsPagination($sql, $filter, $page);
        $offset = $pagination['page'] * self::PAGE_SIZE;
        $sql .= " GROUP BY `request`.`id` ORDER BY " . self::$order_fields[$filter['order_field']] . " {$filter['order']} LIMIT $offset, " . self::PAGE_SIZE;
        $requests = $this->db->query($sql)->result_object();
        $this->load->view('header');
        $this->load->view('adminrequests/index', array(
            'filter' => $filter,
            'requests' => $requests,
            'pagination' => $pagination,
            'filters' => $this->db->query("SELECT * FROM `filter` WHERE `client_id` = {$this->CurrentAccountModel->client_id} ORDER BY `name`")->result_object()
        ));
        $this->load->view('footer');
    }

    private function getRequestsSQL($filter)
    {
        $sql = "SELECT `request`.*, `province`.`name` AS `province_name`, CONCAT(`request`.`first_name`, ' ', `request`.`last_name`) AS `borrower`, COUNT(`client_request`.`id`) AS `sold_count`
                FROM `request`
                LEFT JOIN `client_request` ON `client_request`.`request_id` = `request`.`id`
                LEFT JOIN `province` ON `province`.`id` = `request`.`province_id`
                WHERE 1=1";
        if (!empty($filter['start_date']))
            $sql .= " AND `request`.`date` >= {$this->db->escape(date('Y-m-d 00:00:00', strtotime($filter['start_date'])))}";
        if (!empty($filter['end_date']))
            $sql .= " AND `request`.`date` < {$this->db->escape(date('Y-m-d 23:59:59', strtotime($filter['end_date'])))}";
        if (!$filter['show_debug'])
            $sql .= " AND `request`.`mode` = 'P'";
        if ($filter['hide_rejected'])
            $sql .= " AND `request`.`status` = TRUE";
        if ($filter['hide_succeeded'])
            $sql .= " AND `request`.`status` <> TRUE";
        return $sql;
    }
    
    private function getRequestsPagination($sql, $filter, $page)
    {
        $sql = str_replace("`request`.*", "IFNULL(COUNT(`request`.`id`), 0) AS `count`", $sql);
        $row = $this->db->query($sql)->row();
        $count = empty($row) ? 0 : $row->count;
        $pagination = self::buildPagination($count, self::PAGE_SIZE, $page);
        $filter['page'] = $pagination['page'];
        $this->setFilter($filter);
        return $pagination;
    }
    
    public function view()
    {
        $id = intval($this->input->get('id'));
        $model = $this->db->query("SELECT `request`.*, `p1`.`name` AS `province_name`, `p2`.`name` AS `employer_province_name`, `p3`.`name` AS `bank_province_name`
            FROM `request`
            LEFT JOIN `province` AS `p1` ON `p1`.`id` = `request`.`province_id`
            LEFT JOIN `province` AS `p2` ON `p2`.`id` = `request`.`employer_province_id`
            LEFT JOIN `province` AS `p3` ON `p3`.`id` = `request`.`bank_province_id`
            WHERE `request`.`id` = $id
            LIMIT 1
        ")->row();
        $clients = $this->db->query("SELECT `client`.*, `client_request`.`amount` AS `revenue`, `filter`.`name` AS `filter_name`
            FROM `client_request`
            INNER JOIN `client` ON `client`.`id` = `client_request`.`client_id`
            INNER JOIN `filter` ON `filter`.`id` = `client_request`.`filter_id`
            WHERE `client_request`.`request_id` = $id
        ")->result_object();
        $this->load->view('header');
        $this->load->view('adminrequests/view', array('model' => $model, 'clients' => $clients));
        $this->load->view('footer');
    }
    
    public function filter()
    {
        $filter = $this->getFilter();
        $this->setFilter($filter);
        redirect(base_url() . 'adminrequests/index');
    }
    
    public function export()
    {
        $start = $this->input->get('start');
        $end = $this->input->get('end');
        $sql = "SELECT
                `request`.`id`,
                `request`.`requested_amount`,
                `request`.`first_name`,
                `request`.`last_name`,
                `request`.`email`,
                `request`.`home_phone`,
                `request`.`loan_term`,
                `request`.`address`,
                `request`.`barangay`,
                `request`.`address_2`,
                `request`.`city`,
                `p1`.`name` AS `province`,
                `request`.`postal_code`,
                `request`.`residence_type`,
                `request`.`address_months`,
                `request`.`cell_phone`,
                `request`.`contact_time`,
                `request`.`sss`,
                `request`.`tin`,
                `request`.`dob`,
                `request`.`education_level`,
                `request`.`gender`,
                `request`.`mothers_name`,
                `request`.`mother_dob`,
                `request`.`civil_status`,
                `request`.`spouses_name`,
                `request`.`spouses_dob`,
                `request`.`employed`,
                `request`.`self_employed`,
                `request`.`employer_name`,
                `request`.`job_title`,
                `request`.`months_employed`,
                `request`.`net_income`,
                `request`.`work_phone`,
                `request`.`work_extension`,
                `request`.`employer_address`,
                `request`.`employer_city`,
                `p2`.`name` AS `employer_province`,
                `request`.`work_email`,
                `request`.`prev_employer`,
                `request`.`prev_start`,
                `request`.`prev_income`,
                `request`.`bank_account`,
                `request`.`bank_name`,
                `request`.`bank_address`,
                `request`.`bank_city`,
                `p3`.`name` AS `bank_province`,
                `request`.`credit_card`,
                `request`.`card_issuer`,
                `request`.`personal_references`,
                `request`.`reference_fullname_1`,
                `request`.`reference_phone_1`,
                `request`.`reference_fullname_2`,
                `request`.`reference_phone_2`,
                `request`.`date`,
                `request`.`status`,
                `request`.`status_message`,
                `request`.`mode`,
                `request`.`ip_address`,
                `request`.`source_url`
                
                FROM `request`
                LEFT JOIN `province` AS `p1` ON `p1`.`id` = `request`.`province_id`
                LEFT JOIN `province` AS `p2` ON `p2`.`id` = `request`.`employer_province_id`
                LEFT JOIN `province` AS `p3` ON `p3`.`id` = `request`.`bank_province_id`";
        if (!empty($start) && !empty($end) && strtotime($start) && strtotime($end))
            $sql .= " WHERE `request`.`date` BETWEEN '" . date('Y-m-d 00:00:00', strtotime($start)) . "' AND '" . date('Y-m-d 23:59:59', strtotime($end)) .  "'";
        $data = $this->db->query($sql)->result_array();
        header('Content-Type: application/csv');
        header('Content-Disposition: attachement; filename="export.csv";');
        
        $line = array('ID',
                'Requested Amount',
                'First Name',
                'Last Name',
                'Email',
                'Home Phone',
                'Loan Term',
                'Address',
                'Barangay',
                'Addres(cont)',
                'City',
                'Province',
                'Postal Code',
                'Residence Type',
                'Address Months',
                'Cell Phone',
                'Contact Time',
                'SSS',
                'TIN',
                'Date of Birth',
                'Education Level',
                'Gender',
                'Mother\'s Name',
                'Mother\'s Date of Birth',
                'Civil Status',
                'Spouse\'s Name',
                'Spouse\'s Date of Birth',
                'Employed',
                'Self-Employed',
                'Employer Name',
                'Job Title',
                'Months Employed',
                'Net Income',
                'Work Phone',
                'Work Extension',
                'Employer Address',
                'Employer City',
                'Employer Province',
                'Work Email',
                'Prev Employer',
                'Prev Start',
                'Prev Income',
                'Bank Account',
                'Bank Name',
                'Bank Address',
                'Bank City',
                'Bank Province',
                'Have Credit Card',
                'Card Issuer',
                'Have Personal References',
                'First Reference Full Name',
                'First Reference Phone',
                'Second Reference Full Name',
                'Second Reference Phone',
                'Date',
                'Status',
                'Status Message',
                'Mode',
                'IP Address',
                'Source URL');
        echo '"' . implode('","', $line) . '"' . "\n";
        foreach($data as $line)
            echo '"' . implode('","', $line) . '"' . "\n";
    }
}