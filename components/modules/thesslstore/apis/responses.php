<?php
/**
 * User: Parag Mehta<parag@paragm.com>
 * Date: 2/27/12
 * Time: 7:27 AM
 * This file is created by www.thesslstore.com for your use. You are free to change the file as per your needs.
 */

class csr_response extends baseresponse
{
	public $DominName;
	public $DNSNames;
	public $Country;
	public $Email;
	public $Locality;
	public $Organization;
	public $OrganisationUnit;
	public $State;
	public $hasBadExtensions = false;
	public $isValidDomainName = false;
	public $isWildcardCSR = false;
}

class free_claimfree_response extends baseresponse
{
    public $isAllowed;
    public $PartnerOrderID;
    public $LoginName;
    public $LoginPassword;
}

class free_cuinfo_response extends baseresponse
{
    public $isSupported;
    public $Months;
    public $SerialNumber;
    public $ExpirationDate;
    public $Issuer;
}

class health_validate_response extends baseresponse
{
    public $Status;
}


class order_download_response extends baseresponse
{
    public $PartnerOrderID;
    public $CertificateStartDate;
    public $CertificateEndDate;
    public $CertificateStatus;
    public $ValidationStatus;
    public $Certificates;
}

class quickapproverlist_response extends baseresponse
{
	public $ApproverList;
}

class order_response extends baseresponse
{
    public function __construct()
    {
        $this->OrderStatus = new orderStatus();
        parent::__construct();
    }
    public $PartnerOrderID;
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $VendorOrderID;
    public $RefundRequestID;
    public $isRefundApproved;
    public $TinyOrderLink;
    public $OrderStatus;
    public $OrderAmount;
    public $CertificateStartDate;
    public $CertificateEndDate;
    public $CommonName;
    public $DNSNames;
    public $State;
    public $Country;
    public $Locality;
    public $Organization;
    public $OrganizationalUnit;
    public $WebServerType;
    public $ReissueSuccessCode;
}

class order_approverlist_response extends baseresponse
{
    public $ApproverEmailList;
}

class order_agreement_response extends baseresponse
{
    public $Agreement;
}

class user_subuser_response extends baseresponse
{
    public $PartnerCode;
    public $CustomPartnerCode;
    public $AuthenticationToken;
    public $PartnerEmail;
    public $isEnabled;
}

class order_download_zipresponse extends baseresponse
{
    public $PartnerOrderID;
    public $CertificateStartDate;
    public $CertificateEndDate;
    public $CertificateStatus;
    public $ValidationStatus;
    public $Certificates;
    public $Zip;
}

class order_query_response extends baseresponse
{
    public $CertificateStartDate;
    public $CertificateEndDate;
    public $CommonName;
    public $Country;
    public $CustomOrderID;
    public $DNSNames;
	public $isRefundApproved;
    public $Locality;
    public $OrderAmount;
    public $Organization;
    public $OrganizationalUnit;
    public $PartnerOrderID;
	public $RefundRequestID;
    public $ReissueSuccessCode;
    public $State;
	public $TheSSLStoreOrderID;
    public $TinyOrderLink;
    public $VendorOrderID;
    public $WebServerType;
    public $isTinyOrder;
    public $isTinyOrderClaimed;
	public $MajorStatus;
    public $MinorStatus;
}
