<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
/**
 * @package   Jagger
 * @author    Middleware Team HEAnet
 * @author    Janusz Ulanowski <janusz.ulanowski@heanet.ie>
 * @copyright 2016 HEAnet Limited (http://www.heanet.ie)
 * @license   MIT http://www.opensource.org/licenses/mit-license.php
 */

class Ec extends MY_Controller
{

    function __construct() {
        parent::__construct();
        $this->load->helper('form');
        $this->load->library('form_validation');

    }

    public function show($entcatId = null) {


        if ($entcatId !== null && !ctype_digit($entcatId)) {
            show_error('Argument passed to page  not allowed', 403);
        }
        $loggedin = $this->jauth->isLoggedIn();
        if (!$loggedin) {
            redirect('auth/login', 'location');
        }
        $this->load->library('zacl');
        $this->title = lang('title_entcats');
        $hasWriteAccess = $this->zacl->check_acl('coc', 'write', 'default', '');
        /**
         * @var models\Coc[] $entCategories
         */
        $entCategories = $this->em->getRepository("models\Coc")->findBy(array('type' => 'entcat'));
        $data['rows'] = array();
        if (is_array($entCategories) && count($entCategories) > 0) {
            foreach ($entCategories as $entCat) {
                $countProviders = $entCat->getProvidersCount();
                $isEnabled = $entCat->getAvailable();
                $linetxt = '';
                if ($hasWriteAccess) {
                    $linetxt = '<a href="' . base_url() . 'manage/ec/edit/' . $entCat->getId() . '" ><i class="fi-pencil"></i></a>';
                    if (!$isEnabled) {
                        $linetxt .= '&nbsp;&nbsp;<a href="' . base_url() . 'manage/ec/remove/' . $entCat->getId() . '" class="withconfirm" data-jagger-fieldname="' . $entCat->getName() . '" data-jagger-ec="' . $entCat->getId() . '" data-jagger-counter="' . $countProviders . '"><i class="fi-trash"></i></a>';
                    }
                }

                $lbl = '<span class="lbl lbl-disabled">' . lang('rr_disabled') . '</span>';
                if ($isEnabled) {
                    $lbl = '<span class="lbl lbl-active">' . lang('rr_enabled') . '</span>';
                }
                $lbl .= '<span class="label secondary ecmembers" data-jagger-jsource="' . base_url('manage/regpolicy/getmembers/' . $entCat->getId() . '') . '">' . $countProviders . '</span> ';
                $subtype = $entCat->getSubtype();
                if (empty($subtype)) {
                    $subtype = '<span class="label alert">' . lang('lbl_missing') . '</span>';
                }
                $data['rows'][] = array($entCat->getName(), $subtype, anchor($entCat->getUrl(), $entCat->getUrl(), array('target' => '_blank', 'class' => 'new_window')), $entCat->getDescription(), $lbl, $linetxt);
            }
        } else {
            $data['error_message'] = lang('rr_noentcatsregistered');
        }
        $data['showaddbutton'] = false;
        if ($hasWriteAccess) {
            $data['showaddbutton'] = true;
        }

        $data['titlepage'] = lang('ent_list_title');

        $data['breadcrumbs'] = array(
            array('url' => '#', 'name' => lang('entcats_menulink'), 'type' => 'current'),
        );
        $data['content_view'] = 'manage/coc_show_view';
        $this->load->view('page', $data);
    }

    function getMembers($ecid) {
        if (!$this->input->is_ajax_request() || !$this->jauth->isLoggedIn()) {
            return $this->output->set_status_header(403)->set_output('Access denied');
        }

        $this->load->library('zacl');
        $myLang = MY_Controller::getLang();
        /**
         * @var $entCategory models\Coc
         */
        $entCategory = $this->em->getRepository("models\Coc")->findOneBy(array('id' => $ecid));
        if (empty($entCategory)) {
            return $this->output->set_status_header(404)->set_output('no members found');
        }
        /**
         * @var $ecMembers models\Provider[]
         */
        $ecMembers = $entCategory->getProviders();
        $result = array();
        foreach ($ecMembers as $member) {
            $result[] = array(
                'entityid' => $member->getEntityId(),
                'provid' => $member->getId(),
                'name' => $member->getNameToWebInLang($myLang),
            );
        }
        return $this->output->set_content_type('application/json')->set_output(json_encode($result));
    }

    private function _add_submit_validate() {
        $this->form_validation->set_rules('name', lang('entcat_displayname'), 'required|trim|cocname_unique');
        $this->form_validation->set_rules('attrname', lang('rr_attr_name'), 'required|trim|xss_clean');
        $attrname = $this->input->post('attrname');
        $this->form_validation->set_rules('url', lang('entcat_value'), 'required|trim|valid_url|ecUrlInsert[' . $attrname . ']');
        $this->form_validation->set_rules('description', lang('entcat_description'), 'xss_clean');
        $this->form_validation->set_rules('cenabled', lang('entcat_enabled'), 'xss_clean');
        return $this->form_validation->run();
    }

    private function _edit_submit_validate($entcatId) {
        $attrname = $this->input->post('attrname');
        $this->form_validation->set_rules('name', lang('entcat_displayname'), 'required|trim|cocname_unique_update[' . $entcatId . ']');
        $this->form_validation->set_rules('attrname', lang('rr_attr_name'), 'required|trim');
        $ecUrlUpdateParams = serialize(array('id' => $entcatId, 'subtype' => $attrname));
        $this->form_validation->set_rules('url', lang('entcat_value'), 'required|trim|valid_url|ecUrlUpdate[' . $ecUrlUpdateParams . ']');
        $this->form_validation->set_rules('description', lang('entcat_description'), 'xss_clean');
        $this->form_validation->set_rules('cenabled', lang('entcat_enabled'), 'xss_clean');
        return $this->form_validation->run();
    }

    public function add() {

        $loggedin = $this->jauth->isLoggedIn();
        if (!$loggedin) {
            redirect('auth/login', 'location');
        }
        $this->load->library('zacl');
        $this->title = lang('title_addentcat');
        $data['titlepage'] = lang('title_addentcat');
        $hasWriteAccess = $this->zacl->check_acl('coc', 'write', 'default', '');
        if (!$hasWriteAccess) {
            show_error('No access', 401);
        }

        if ($this->_add_submit_validate() === TRUE) {
            $name = $this->input->post('name');
            $url = $this->input->post('url');
            $cenabled = $this->input->post('cenabled');
            $description = $this->input->post('description');

            $ncoc = new models\Coc;
            $ncoc->setName($name);
            $ncoc->setUrl($url);
            $ncoc->setType('entcat');
            $allowedattrs = attrsEntCategoryList();
            $inputAttrname = $this->input->post('attrname');
            if (in_array($inputAttrname, $allowedattrs)) {
                $ncoc->setSubtype($inputAttrname);
            }
            if (!empty($description)) {
                $ncoc->setDescription($description);
            }
            if (!empty($cenabled) && $cenabled == 'accept') {
                $ncoc->setAvailable(TRUE);
            } else {
                $ncoc->setAvailable(FALSE);
            }
            $this->em->persist($ncoc);
            $this->em->flush();

            $data['success_message'] = lang('rr_entcatadded');
        } else {
            $form = form_open();
            $this->load->library('formelement');
            $form .= $this->formelement->generateAddCoc();
            $form .= '<div class="buttons small-12 medium-10 large-10 columns end text-right">';
            $form .= '<button type="reset" name="reset" value="reset" class="resetbutton reseticon alert">' . lang('rr_reset') . '</button> ';
            $form .= '<button type="submit" name="modify" value="submit" class="savebutton saveicon">' . lang('rr_save') . '</button></div>';

            $form .= form_close();
            $data['form'] = $form;
        }
        $data['breadcrumbs'] = array(
            array('url' => base_url('manage/ec/show'), 'name' => lang('title_entcats')),
            array('url' => '#', 'name' => lang('title_addentcat'), 'type' => 'current'),
        );
        $data['content_view'] = 'manage/coc_add_view';
        $this->load->view('page', $data);
    }

    public function edit($entcatId) {

        $loggedin = $this->jauth->isLoggedIn();
        if (!$loggedin) {
            redirect('auth/login', 'location');
        }
        $this->load->library('zacl');
        $this->title = lang('title_entcatedit');

        if (!ctype_digit($entcatId)) {
            show_error('Not found', 404);
        }
        /**
         * @var models\Coc $coc
         */
        $coc = $this->em->getRepository("models\Coc")->findOneBy(array('id' => $entcatId, 'type' => 'entcat'));
        if ($coc === null) {
            show_error('Not found', 404);
        }
        $hasWriteAccess = $this->zacl->check_acl('coc', 'write', 'default', '');
        if (!$hasWriteAccess) {
            show_error('No access', 401);
        }
        $data['titlepage'] = lang('title_entcat') . ': ' . html_escape($coc->getName());
        $data['subtitlepage'] = lang('title_entcatedit');

        if ($this->_edit_submit_validate($entcatId) === true) {
            $enable = $this->input->post('cenabled');
            if (!empty($enable) && $enable == 'accept') {
                $coc->setAvailable(true);
            } else {
                $coc->setAvailable(false);
            }
            $coc->setName($this->input->post('name'));
            $coc->setUrl($this->input->post('url'));
            $allowedattrs = attrsEntCategoryList();
            $inputAttrname = $this->input->post('attrname');
            if (in_array($inputAttrname, $allowedattrs)) {
                $coc->setSubtype($inputAttrname);
            }
            $coc->setDescription($this->input->post('description'));
            $this->em->persist($coc);
            $this->em->flush();
            $data['success_message'] = lang('updated');
        }
        $data['coc_name'] = $coc->getName();
        $this->load->library('formelement');
        $form = form_open();
        $form .= $this->formelement->generateEditCoc($coc);
        $form .= '<div class="buttons large-10 medium-10 small-12 text-right columns end">';
        $form .= '<button type="reset" name="reset" value="reset" class="resetbutton reseticon alert">' . lang('rr_reset') . '</button> ';
        $form .= '<button type="submit" name="modify" value="submit" class="savebutton saveicon">' . lang('rr_save') . '</button></div>';
        $form .= form_close();
        $data['form'] = $form;
        $data['breadcrumbs'] = array(
            array('url' => base_url('manage/ec/show'), 'name' => lang('title_entcats')),
            array('url' => '#', 'name' => lang('title_editform'), 'type' => 'current'),
        );
        $data['content_view'] = 'manage/coc_edit_view';
        $this->load->view('page', $data);
    }

    public function remove($entcatId = null) {
        if (!ctype_digit($entcatId)) {
            return $this->output->set_status_header(404)->set_output('incorrect id or id not provided');
        }
        if (!$this->input->is_ajax_request()) {
            return $this->output->set_status_header(403)->set_output('access denied');
        }
        $loggedin = $this->jauth->isLoggedIn();
        if (!$loggedin) {
            return $this->output->set_status_header(403)->set_output('access denied');
        }

        $this->load->library('zacl');
        $hasWriteAccess = $this->zacl->check_acl('coc', 'write', 'default', '');
        if (!$hasWriteAccess) {
            return $this->output->set_status_header(403)->set_output('access denied');
        }
        $entcat = $this->em->getRepository("models\Coc")->findOneBy(array('id' => '' . $entcatId . '', 'type' => 'entcat', 'is_enabled' => false));
        if ($entcat === null) {
            return $this->output->set_status_header(403)->set_output('Registration policy doesnt exist or is not disabled');
        }
        /**
         * @var models\AttributeReleasePolicy[] $arps
         */
        $arps = $this->em->getRepository('models\AttributeReleasePolicy')->findBy(array('type' => 'entcat', 'requester' => $entcat->getId()));
        $this->em->remove($entcat);
        foreach ($arps as $arp) {
            $this->em->remove($arp);
        }
        try {
            $this->em->flush();
            return $this->output->set_status_header(200)->set_output('OK');
        } catch (Exception $e) {
            log_message('error', __METHOD__ . ' ' . $e);
            return $this->output->set_status_header(500)->set_output('Internal server error');
        }
    }
}
