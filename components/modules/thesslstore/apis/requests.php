<?php
/**
 * User: Parag Mehta<parag@paragm.com>
 * Date: 2/27/12
 * Time: 7:27 AM
 * This file is created by www.thesslstore.com for your use. You are free to change the file as per your needs.
 */

class csr_request extends baserequest
{
    public $ProductCode = '';
	public $CSR = '';
}

class free_claimfree_request extends baserequest
{
    public function __construct()
    {
        $this->NewOrderRequest = new order_neworder_request();
        parent::__construct();
    }
    public $ProductCode;
    public $RelatedTheSSLStoreOrderID;
    public $NewOrderRequest;
}


class free_cuinfo_request extends baserequest
{
    public function __construct()
    {
        $this->OrganisationInfo = new organizationInfo();
        $this->OrganisationInfo->OrganizationAddress = new organizationAddress();
        $this->AdminContact = new contact();
        $this->TechnicalContact = new contact();
        parent::__construct();
    }

    public $CustomOrderID;
    public $ProductCode;
    public $ExtraProductCodes;
    public $OrganisationInfo;
    public $ValidityPeriod;
    public $ServerCount;
    public $CSR;
    public $DomainName;
    public $WebServerType;
    public $DNSNames;
    public $isCUOrder;
    public $isRenewalOrder;
    public $SpecialInstructions;
    public $RelatedTheSSLStoreOrderID;
    public $isTrialOrder;
    public $AdminContact;
    public $TechnicalContact;
    public $ApproverEmail;
    public $ReserveSANCount;
    public $AddInstallationSupport;
    public $EmailLanguageCode;
}

class health_validate_request
{
    public $PartnerCode;
    public $AuthToken;
    public $ReplayToken;
    public $UserAgent;
}

class order_agreement_request extends baserequest
{
    public function __construct()
    {
        $this->OrganisationInfo = new organizationInfo();
        $this->OrganisationInfo->OrganizationAddress = new organizationAddress();
        parent::__construct();
    }
    public $CustomOrderID;
    public $ProductCode;
    public $ExtraProductCodes;
    public $OrganisationInfo;
    public $ValidityPeriod;
    public $ServerCount;
    public $CSR;
    public $DomainName;
    public $WebServerType;
}

class order_download_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmailType;
    public $RefundReason;
    public $RefundRequestID;
}

class order_inviteorder_request extends baserequest
{
    public $PreferVendorLink;
    public $ProductCode;
    public $ExtraProductCode;
    public $ServerCount;
    public $RequestorEmail;
    public $ExtraSAN;
    public $CustomOrderID;
    public $ValidityPeriod;
    public $AddInstallationSupport;
    public $EmailLanguageCode;
}

class order_neworder_request extends baserequest
{
    public function __construct()
    {
        $this->OrganisationInfo = new organizationInfo();
        $this->OrganisationInfo->OrganizationAddress = new organizationAddress();
        $this->AdminContact= new contact();
        $this->TechnicalContact= new contact();
        parent::__construct();
    }
    public $CustomOrderID;
    public $ProductCode;
    public $ExtraProductCodes;
    public $OrganisationInfo;
    public $ValidityPeriod;
    public $ServerCount;
    public $CSR;
    public $DomainName;
    public $WebServerType;
    public $DNSNames;
    public $isCUOrder;
    public $isRenewalOrder;
    public $SpecialInstructions;
    public $RelatedTheSSLStoreOrderID;
    public $isTrialOrder;
    public $AdminContact;
    public $TechnicalContact;
    public $ApproverEmail;
    public $ReserveSANCount;
    public $AddInstallationSupport;
    public $EmailLanguageCode;
	public $FileAuthDVIndicator;
}

class order_query_request extends baserequest
{
    public $StartDate;
    public $EndDate;
    public $SubUserID;
    public $ProductCode;
}

class order_refundrequest_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmailType;
    public $RefundReason;
    public $RefundRequestID;
}

class order_refundstatus_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmailType;
    public $RefundReason;
    public $RefundRequestID;
}

class order_reissue_request extends baserequest
{
    public function __construct()
    {
        $this->EditSAN = array();
        $this->DeleteSAN = array();
        $this->AddSAN = array();
        parent::__construct();
    }
    public $PartnerOrderID;
    public $CSR;
    public $WebServerType;
    public $DNSNames;
    public $isRenewalOrder;
    public $SpecialInstructions;
    public $EditSAN;
    public $DeleteSAN;
    public $AddSAN;
    public $isWildCard;
    public $ReissueEmail;
}

class order_resend_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmailType;
    public $RefundReason;
    public $RefundRequestID;
}

class order_changeapproveremail_request extends baserequest
{
    public $TheSSLStoreOrderID;
    public $ResendEmail;
}

class order_status_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmailType;
    public $RefundReason;
    public $RefundRequestID;
}


class product_query_request extends baserequest
{
    public $ProductCode;
    public $ProductType;
}

class setting_setordercallback_request extends baserequest
{
    public $url;
}

class setting_setpricecallback_request extends baserequest
{
    public $url;
}

class setting_settemplate_request extends baserequest
{
    public $EmailSubject;
    public $EmailMessage;
    public $isDisabled;
    public $ReminderTemplateDays;
}

class user_add_request extends baserequest
{
    public $CustomPartnerCode;
    public $AuthenticationToken;
    public $PartnerEmail;
}

class user_activate_request extends baserequest
{
    public $PartnerCode;
    public $CustomPartnerCode;
    public $AuthenticationToken;
    public $PartnerEmail;
    public $isEnabled;
}

class user_deactivate_request extends baserequest
{
    public $PartnerCode;
    public $CustomPartnerCode;
    public $AuthenticationToken;
    public $PartnerEmail;
    public $isEnabled;
}

class user_query_request extends baserequest
{
    public $SubUserID;
}

