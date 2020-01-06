<?php
// Uses the TcpdfWrapper for rendering the PDF
Loader::load(COMPONENTDIR . "invoice_templates" . DS . "tcpdf_wrapper.php");
/**
 * Quickbooks Invoice Template
 *
 * @package blesta
 * @subpackage blesta.components.invoice_templates.quickbooks_invoice
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class QuickbooksInvoicePdf extends TcpdfWrapper {
	/**
	 * @var string Holds the default font size for this document
	 */
	private static $font_size = 9;
	/**
	 * @var string Holds the alternate font size for this document
	 */
	private static $font_size_alt = 7;
	/**
	 * @var string Holds the second alternate font size for this document
	 */
	private static $font_size_alt2 = 10;
	/**
	 * @var string Holds the third alternate font size for this document
	 */
	private static $font_size_alt3 = 20;
	/**
	 * @var string The primary font family to use
	 */
	private $font = "dejavusanscondensed";
	/**
	 * @var array An RGB representation of the primary color used throughout
	 */
	private static $primary_color = array(0, 0, 0);
	/**
	 * @var array An RGB representation of the primary text color used throughout
	 */
	private static $primary_text_color = array(0, 0, 0);
	/**
	 * @var array The standard number format options
	 */
	private static $standard_num_options = array('prefix'=>false,'suffix'=>false,'code'=>false,'with_separator'=>false);
	/**
	 * @var array An array of meta data for this invoice
	 */
	public $meta = array();
	/**
	 * @var CurrencyFormat The CurrencyFormat object used to format currency values
	 */
	public $CurrencyFormat;
	/**
	 * @var Date The Date object used to format date values
	 */
	public $Date;
	/**
	 * @var array An array of invoice data for this invoice
	 */
	public $invoice = array();
	/**
	 * @var int The Y position where the header finishes its content
	 */
	private $header_end_y = 0;
	/**
	 * @var array An array of line item options
	 */
	private $line_options = array();
	/**
	 * @var array An array of transaction payment row options
	 */
	private $payment_options = array();
	/**
	 * @var int The y_pos to start the table headings at
	 */
	private $table_heading_y_pos = 233;
	/**
	 * @param boolean Whether to include the to address or not
	 */
	public $include_address = true;
	
	public function __construct($orientation='P', $unit='mm', $format='A4', $unicode=true, $encoding='UTF-8', $diskcache=false, $font=null) {
		
		// Invoke the parent constructor
		parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache);
		
		$this->line_options = array(
			'font_size'=>self::$font_size,
			'x_pos'=>44,
			'y_pos'=>$this->table_heading_y_pos,
			'border'=>'RL',
			'height'=>22,
			'line_style'=>array('width'=>0.5,'cap'=>"butt",'join'=>"miter",'color'=>self::$primary_color),
			'font_size'=>self::$font_size_alt,
			'padding'=>self::$font_size_alt,
			'col'=> array(
				'name'=> array(
					'width'=>314
				),
				'qty'=> array(
					'width'=>70,
					'align'=>'C'
				),
				'unit_price'=> array(
					'width'=>70,
					'align'=>'R'
				),				
				'price'=> array(
					'width'=>70,
					'align'=>'R',
					//'border'=>"B"
				),
			),
			'cell'=>array(array('name'=>array('align'=>'L')))
		);
		
		$this->payment_options = array(
			'x_pos'=>44,
			'y_pos'=>$this->table_heading_y_pos,
			'border'=>"RL",
			'height'=>22,
			'line_style'=>array('width'=>0.5,'cap'=>"butt",'join'=>"miter",'color'=>self::$primary_color),
			'font_size'=>self::$font_size_alt,
			'padding'=>self::$font_size_alt,
			'col'=> array(
				'applied_date'=> array(
					'width'=>131
				),
				'type_name'=> array(
					'width'=>131,
					'align'=>'C'
				),
				'transaction_id'=> array(
					'width'=>131,
					'align'=>'R'
				),				
				'applied_amount'=> array(
					'width'=>131,
					'align'=>'R',
				),
			),
			'cell'=>array(array('applied_date'=>array('align'=>'L')))
		);
		
		// Set tag to use on page numbering replacement
		$this->AliasNbPages("{P}");

		// Set image scale factor
		$this->setImageScale(2); 
		
		// Default monospaced font
		$this->SetDefaultMonospacedFont("courier");
		
		$this->setFontInfo($font);
		
		// Set margins
		$this->SetMargins(44, 260, 44);
		$this->SetFooterMargin(160);
		
		// Set auto page breaks y-px from the bottom of the page
		$this->SetAutoPageBreak(true, 190);
	}
	
	/**
	 * Overwrite the default header that appears on each page of the PDF
	 */
	public function Header() {
		// Draw the background
		$this->drawBackground();
		
		// Draw the paid watermark
		$this->drawPaidWatermark();
		
		// Set logo
		$this->drawLogo();
		
		// Set the page mark so background images will display correctly
		$this->setPageMark();

		// Draw the return address
		$this->drawReturnAddress();
		
		// Place the invoice type on the document
		$this->drawInvoiceType();

		// Set Address
		if ($this->include_address)
			$this->drawAddress();

		// Add Invoice Number, Customer Number, Invoice Date
		$this->drawInvoiceInfo();
		
		// Draw due date
		$this->drawInvoiceSubInfo();
		
		// Draw the line items table heading on each page
		$this->drawLineHeader();
		
		// Set the position where the header finishes
		$this->header_end_y = $this->GetY();
		
		// Set the top margin again, incase any header methods expanded this area.
		$this->SetTopMargin($this->header_end_y);
	}
	
	/**
	 * Overwrite the default footer that appears on each page of the PDF
	 */
	public function Footer() {

		// Set the terms of the document
		if (!empty($this->meta['terms']))
			$this->drawTerms();
		
		// Set the page number of the document
		$this->drawPageNumber();
	}
	
	/**
	 * Draws a complete invoice
	 */
	public function drawInvoice() {
		
		// Create a clone of the invoice in order to determine which line items
		// end each page
		$clone = clone $this;
		$options = $clone->line_options;
		$options['y_pos'] = max($clone->header_end_y, $clone->GetY());
		
		$last_lines = array();
		$page = $clone->getPage();
		
		for ($i=0; $i<count($clone->invoice->line_items); $i++) {
			$line = array(
				'name'=>$clone->invoice->line_items[$i]->description,
				'qty'=>AppModel::truncateDecimal($clone->invoice->line_items[$i]->qty, 0),
				'unit_price'=>$this->CurrencyFormat->format($clone->invoice->line_items[$i]->amount, $clone->invoice->currency, self::$standard_num_options),
				'price'=>$clone->CurrencyFormat->format($clone->invoice->line_items[$i]->total, $clone->invoice->currency, self::$standard_num_options),
			);
			
			// Draw invoice line
			$clone->drawTable(array($line), $options);
			$current_page = $page;
			$page = $clone->getPage();
			$options['y_pos'] = $clone->GetY();
			
			// The previous line item ended the previous page
			if ($current_page != $page) {
				// Save the line item index
				$last_lines[] = ($i-1 < 0 ? 0 : $i-1);
			}
		}
		
		unset($clone);
		
		// Build the actual line items
		$lines = array();
		$options = $this->line_options;
		$options['y_pos'] = max($this->header_end_y, $this->GetY());
		
		for ($i=0; $i<count($this->invoice->line_items); $i++) {
			$lines[] = array(
				'name'=>$this->invoice->line_items[$i]->description,
				'qty'=>AppModel::truncateDecimal($this->invoice->line_items[$i]->qty, 0),
				'unit_price'=>$this->CurrencyFormat->format($this->invoice->line_items[$i]->amount, $this->invoice->currency, self::$standard_num_options),
				'price'=>$this->CurrencyFormat->format($this->invoice->line_items[$i]->total, $this->invoice->currency, self::$standard_num_options),
			);
			
			// Set a border on the last items on the page
			if (in_array($i, $last_lines))
				$options['row'][$i] = array('border'=>"BLR");
		}
		
		// Draw invoice lines
		$this->drawTable($lines, $options);
		
		// Draw public notes and invoice tallies
		$this->drawTallies();
		
		// Draw transaction payments/credits
		$this->drawPayments();
	}
	
	/**
	 * Set the fonts and font attributes to be used in the document
	 */
	private function setFontInfo($font) {
		$lang = array();
		$lang['a_meta_charset'] = 'UTF-8';
		$lang['a_meta_dir'] = Language::_("AppController.lang.dir", true) ? Language::_("AppController.lang.dir", true) : 'ltr';
		
		// Set language settings
		$this->setLanguageArray($lang);
		
		if ($font)
			$this->font = $font;
		
		// Set font
		$this->SetFont($this->font, '', self::$font_size);
		
		// Set default text color
		$this->SetTextColorArray(self::$primary_text_color);
	}
	
	/**
	 * Draws the paid text in the background of the invoice
	 */
	private function drawPaidWatermark() {
		// Show paid watermark
		if (!empty($this->meta['display_paid_watermark']) && $this->meta['display_paid_watermark'] == "true" && ($this->invoice->date_closed != null)) {
			$max_height = $this->getPageHeight();
			$max_width = $this->getPageWidth();
			
			$options = array(
				'x_pos'=>44, // start within margin
				'y_pos'=>($max_height - 125)/2, // vertical center
				'font_size'=>100,
				'row'=>array(array('font_style'=>"B", 'align'=>"C"))
			);
			
			$data = array(
				array('col'=>Language::_("QuickbooksInvoice.watermark_paid", true))
			);
			
			// Set paid background text color
			$this->SetTextColorArray(array(230,230,230));
			
			// Rotate the text
			$this->StartTransform();
			// Rotate 45 degrees from midpoint
			$this->Rotate(45, ($max_width)/2, ($max_height)/2);
			
			$this->drawTable($data, $options);
			
			$this->StopTransform();
			
			// Set default text color
			$this->SetTextColorArray(self::$primary_text_color);
		}
	}
	
	/**
	 * Renders public notes and invoice tallies onto the document
	 */
	private function drawTallies() {
		
		$page = $this->getPage();
		
		$options = array(
			'border'=>1,
			'x_pos'=>44,
			'y_pos'=>max($this->header_end_y, $this->GetY()),
			'font_size'=>self::$font_size_alt,
			'col'=>array(
				array(
					'height'=>12,
					'width'=>384
				)
			)
		);

		
		// Draw notes
		$y_pos = 0;
		if (!empty($this->invoice->note_public)) {
			$data = array(
				array(Language::_("QuickbooksInvoice.notes_heading", true)),
				array($this->invoice->note_public)
			);
			// Draw notes
			$this->drawTable($data, $options);
			$y_pos = $this->GetY();
		}
		
		$this->setPage($page);
		
		// Set subtitle
		$data = array(
			array('notes'=>null,'label'=>Language::_("QuickbooksInvoice.subtotal_heading", true),'price'=>$this->CurrencyFormat->format($this->invoice->subtotal, $this->invoice->currency, self::$standard_num_options))
		);
		// Set all taxes
		foreach ($this->invoice->taxes as $tax) {
			$data[] = array('notes'=>null,'label'=>Language::_("QuickbooksInvoice.tax_heading", true, $tax->name, $tax->amount),'price'=>$this->CurrencyFormat->format($tax->tax_total, $this->invoice->currency, self::$standard_num_options));
		}
		// Set total
		$data[] = array('notes'=>null,'label'=>Language::_("QuickbooksInvoice.total_heading", true),'price'=>$this->CurrencyFormat->format($this->invoice->total, $this->invoice->currency));

		$options['padding'] = self::$font_size_alt;
		$options['col'] = array(
			'notes'=> array(
				'width'=>384,
				'border'=>0
			),
			'label'=> array(
				'width'=>70,
				'align'=>'R',
				'font_style'=>"B"
			),				
			'price'=> array(
				'width'=>70,
				'align'=>'R',
			),
		);
		$options['cell'] = array(array('notes'=>array('border'=>'T')));
		
		// Draw tallies
		$this->drawTable($data, $options);
		
		// Set the Y position to the greater of the notes area, or the subtotal/total area
		$this->SetY(max($y_pos, $this->GetY()));
	}
	
	/**
	 * Renders a heading, typically above a table
	 */
	private function drawTableHeading($heading) {
		$options = $this->payment_options;
		$options = array(
			'font_size'=>self::$font_size,
			'y_pos'=>max($this->table_heading_y_pos, $this->GetY()),
			'border'=>'TRL',
			'height'=>22,
			'line_style'=>array('width'=>0.5,'cap'=>"butt",'join'=>"miter",'color'=>self::$primary_color),
			'padding'=>self::$font_size_alt,
			'col'=> array(
				'heading' => array(
					'width'=>524
				)
			),
			'cell'=>array(array('heading'=>array('align'=>'L')))
		);
		
		// Add space between the end of the previous table and the beginning of this heading
		// iff this heading is not at the top of the page
		if ($this->table_heading_y_pos < $this->GetY())
			$options['y_pos'] = $this->GetY() + 10;
		
		$data = array(
			array('heading' => $heading),
		);
		
		// Draw table heading
		$this->drawTable($data, $options);
	}
	
	/**
	 * Renders the transaction payments/credits header onto the document
	 */
	private function drawPaymentHeader() {
		// Add a heading above the table
		$this->drawTableHeading(Language::_("QuickbooksInvoice.payments_heading", true));
		
		// Use the same options as line items
		$options = $this->payment_options;
		// Start header at top of page, or current page below the other table
		$options['y_pos'] = max($this->table_heading_y_pos, $this->GetY());
		
		// Draw the transaction payment header
		$options['row'] = array(array('font_size'=>self::$font_size, 'border'=>1,'align'=>'C'));
		
		$header = array(array(
			'applied_date' => Language::_("QuickbooksInvoice.payments_applied_date", true),
			'type_name' => Language::_("QuickbooksInvoice.payments_type_name", true),
			'transaction_id' => Language::_("QuickbooksInvoice.payments_transaction_id", true),
			'applied_amount' => Language::_("QuickbooksInvoice.payments_applied_amount", true)
		));
		
		$this->drawTable($header, $options);
	}
	
	/**
	 * Renders the transaction payments/credits section onto the document
	 */
	private function drawPayments() {
		if (!empty($this->meta['display_payments']) && $this->meta['display_payments'] == "true") {
			// Set the payment rows
			$options = $this->payment_options;
			$rows = array();
			$i = 0;
			foreach ($this->invoice->applied_transactions as $applied_transaction) {
				// Only show approved transactions
				if ($applied_transaction->status != "approved")
					continue;
				
				// Use the type name, or the gateway name
				$type_name = $applied_transaction->type_real_name;
				if ($applied_transaction->type == "other" && $applied_transaction->gateway_type == "nonmerchant")
					$type_name = $applied_transaction->gateway_name;
				
				$rows[$i] = array(
					'applied_date' => $this->Date->cast($applied_transaction->applied_date, $this->invoice->client->settings['date_format']),
					'type_name' => $type_name,
					'transaction_id' => $applied_transaction->transaction_number,
					'applied_amount' => $this->CurrencyFormat->format($applied_transaction->applied_amount, $applied_transaction->currency, self::$standard_num_options)
				);
				$i++;
			}
			
			// Don't draw the table if there are no payments
			if (empty($rows))
				return;
			
			// Draw the table headings
			$this->drawPaymentHeader();
			
			// Draw the table rows
			$options['y_pos'] = max($this->table_heading_y_pos, $this->header_end_y, $this->GetY());
			$this->drawTable($rows, $options);
			
			// Set balance due at bottom of table
			$data = array(array('blank'=>"",'label'=>Language::_("QuickbooksInvoice.balance_heading", true),'price'=>$this->CurrencyFormat->format($this->invoice->due, $this->invoice->currency)));
			
			$options['y_pos'] = max($this->table_heading_y_pos, $this->header_end_y, $this->GetY());
			$options['row'] = array('blank'=>array('border'=>0));
			$options['padding'] = self::$font_size_alt;
			$options['col'] = array(
				'blank'=> array(
					'width'=>262,
					'border'=>"T"
				),
				'label'=> array(
					'width'=>131,
					'align'=>'R',
					'border'=>1,
					'font_style' => "B"
				),				
				'price'=> array(
					'width'=>131,
					'align'=>'R',
					'border'=>1
				),
			);
			
			// Draw balance
			$this->drawTable($data, $options);
		}
	}
	
	/**
	 * Renders the background image onto the document
	 */
	private function drawBackground() {
	
        // Set background image by fetching current margin break,
		// then disable page break, set the image, and re-enable page break with
		// the current margin
        $bMargin = $this->getBreakMargin();
        $auto_page_break = $this->AutoPageBreak;
        $this->SetAutoPageBreak(false, 0);
		if (file_exists($this->meta['background']))
			$this->Image($this->meta['background'], 0, 0, 0, 0, '', '', '', false, 300, '', false, false, 0);
			
        // Restore auto-page-break status
        $this->SetAutoPageBreak($auto_page_break, $bMargin);
	}
	
	/**
	 * Renders the logo onto the document
	 */
	private function drawLogo() {
		
		// Really wish we could align right, but aligning right will not respect the
		// $x parameter in TCPDF::Image(), so we must manually set the off-set. That's ok
		// because we're setting the width anyway.
		if ($this->meta['display_logo'] == "true" && file_exists($this->meta['logo']))
			$this->Image($this->meta['logo'], 432, 35, 140);
	}
	
	/**
	 * Fetch the date due to display
	 *
	 * @return string The date due to display
	 */
	protected function getDateDue() {
		$date_due = null;
		switch ($this->invoice->status) {
			case "proforma":
				$due_date_option = "display_due_date_proforma";
				break;
			case "draft":
				$due_date_option = "display_due_date_draft";
				break;
			default:
				$due_date_option = "display_due_date_inv";
				break;
		}
		if ($this->meta[$due_date_option] == "true") {
			$date_due = $this->invoice->date_due;
		}
		return $date_due;
	}

	/**
	 * Renders the Invoice info section to the document, containing the invoice ID, client ID, date billed
	 */
	private function drawInvoiceInfo() {
		// Set the invoice number label language
		$inv_id_code_lang = "QuickbooksInvoice.invoice_id_code";
		if (in_array($this->invoice->status, array("proforma", "draft"))) {
			$inv_id_code_lang = "QuickbooksInvoice." . $this->invoice->status . "_id_code";
		}
		
		$data = array(
			array(
				'client_id'=>Language::_("QuickbooksInvoice.client_id_code", true),
				'date'=>Language::_("QuickbooksInvoice.date_billed", true),
				'invoice_id'=>Language::_($inv_id_code_lang, true)
			),
			array(
				'client_id'=>$this->invoice->client->id_code,
				'date'=>$this->Date->cast($this->invoice->date_billed, $this->invoice->client->settings['date_format']),
				'invoice_id'=>$this->invoice->id_code
			)
		);

		$options = array(
			'font_size'=>self::$font_size,
			'padding'=>self::$font_size/2,
			'x_pos'=>-294,
			'y_pos'=>120,
			'border'=>1,
			'line_style'=>array('width'=>0.5,'cap'=>"butt",'join'=>"miter",'color'=>self::$primary_color),
			'align'=>'C',
			'col' => array(
				'date' => array('width'=>80),
				'client_id' => array('width'=>90),
				'invoice_id' => array('width'=>80),
			)
		);
		$this->drawTable($data, $options);
	}
	
	/**
	 * Renders the Invoice sub info section to the document, containing the date due
	 */
	private function drawInvoiceSubInfo() {
		$date_due = $this->getDateDue();
		if (!$date_due) {
			return;
		}

		$data = array(
			array(
				'date_due'=>Language::_("QuickbooksInvoice.date_due", true)
			),
			array(
				'date_due'=>$this->Date->cast($this->invoice->date_due, $this->invoice->client->settings['date_format'])
			)
		);

		$options = array(
			'font_size'=>self::$font_size,
			'padding'=>self::$font_size/2,
			'x_pos'=>-124,
			'y_pos'=>192,
			'border'=>'LR',
			'line_style'=>array('width'=>0.5,'cap'=>"butt",'join'=>"miter",'color'=>self::$primary_color),
			'align'=>'C',
			'col' => array(
				'date_due' => array('width'=>80)
			),
			'row'=> array(array('border'=>1, 'padding'=>self::$font_size/2))
		);
		$this->drawTable($data, $options);
	}
	
	/**
	 * Renders the line items table heading
	 */
	private function drawLineHeader() {
		$options = $this->line_options;
		$options['row']= array(array('font_size'=>self::$font_size, 'border'=>1,'align'=>'C'));
		
		$header = array(array(
			'name'=>Language::_("QuickbooksInvoice.lines_description", true),
			'qty'=>Language::_("QuickbooksInvoice.lines_quantity", true),
			'unit_price'=>Language::_("QuickbooksInvoice.lines_unit_price", true),
			'price'=>Language::_("QuickbooksInvoice.lines_cost", true)
		));
		$this->drawTable($header, $options);
	}
	
	/**
	 * Renders the to address information
	 */
	private function drawAddress() {
		$data = array(
			array(Language::_("QuickbooksInvoice.address_heading", true)),
		);
		
		$options = array(
			'font_size'=>self::$font_size,
			'x_pos'=>44,
			'y_pos'=>137,
			'align'=>'L',
			'border'=>'LR',
			'col'=> array(
				array('width'=>210)
			),
			'row'=> array(array('border'=>1, 'padding'=>self::$font_size/2))
		);
		
		$this->drawTable($data, $options);
		
		// Draw an empty table box as a frame for the content
		$options['type'] = "cell";
		$options['height'] = 86;
		$data = array();
		$data[] = array("");
		$this->drawTable($data, $options);
		
		// Draw the content
		$data = array(array(null));
		$data[] = array($this->invoice->billing->first_name . " " . $this->invoice->billing->last_name);
		if (strlen($this->invoice->billing->company) > 0)
			$data[] = array($this->invoice->billing->company);
		$data[] = array($this->invoice->billing->address1);
		if (strlen($this->invoice->billing->address2) > 0)
			$data[] = array($this->invoice->billing->address2);
		$data[] = array(Language::_("QuickbooksInvoice.address_city_state", true, $this->invoice->billing->city, $this->invoice->billing->state, $this->invoice->billing->zip, $this->invoice->billing->country->alpha3));
		
		$options['y_pos'] = 150;
		$options['x_pos'] = 49;
		$options['row'][0]['border'] = null;
		unset($options['border'], $options['height']);
		
		$this->drawTable($data, $options);
	}
	
	/**
	 * Renders the return address information including tax ID
	 */
	private function drawReturnAddress() {
		if ($this->meta['display_companyinfo'] == "false")
			return;
		
		$data = array(
			array($this->meta['company_name']),
			array($this->meta['company_address'])
		);
		
		if (isset($this->meta['tax_id']) && $this->meta['tax_id'] != "")
			$data[] = array(Language::_("QuickbooksInvoice.tax_id", true, $this->meta['tax_id']));
		if (isset($this->invoice->client->settings['tax_id']) && $this->invoice->client->settings['tax_id'] != "")
			$data[] = array(Language::_("QuickbooksInvoice.client_tax_id", true, $this->invoice->client->settings['tax_id']));
			
		$options = array(
			'font_size'=>self::$font_size,
			'y_pos'=>38,
			'x_pos'=>44,
			'align'=>'L'
		);
		$this->drawTable($data, $options);
	}
	
	/**
	 * Sets the invoice type on the document based upon the status of the invoice
	 */
	private function drawInvoiceType() {
		$data = array(
			array(Language::_("QuickbooksInvoice.type_" . $this->invoice->status, true))
		);
		$options = array(
			'font_size'=>self::$font_size_alt3,
			'font_style'=>"B",
			'y_pos'=>92,
			'align'=>'R'
		);
		$this->drawTable($data, $options);
	}
	
	/**
	 * Renders the page number to the document
	 */
	private function drawPageNumber() {
		$data = array(
			array(Language::_("QuickbooksInvoice.page_of", true, $this->getGroupPageNo(), $this->getPageGroupAlias()))
		);
		$options = array(
			'font_size'=>self::$font_size_alt,
			'font_style'=>"B",
			'y_pos'=>-52,
			'align'=>'R'
		);
		$this->drawTable($data, $options);
	}
	
	/**
	 * Renders the terms of this document
	 */
	private function drawTerms() {
		$data = array(
			array(Language::_("QuickbooksInvoice.terms_heading", true)),
			array($this->meta['terms'])
		);
		$options = array(
			'font_size'=>self::$font_size_alt,
			'border'=>0,
			'x_pos'=>48,
			'y_pos'=>-119,
			'col'=>array(array('height'=>12)),
			'row'=>array(array('font_style'=>"B"))
		);
		$this->drawTable($data, $options);
	}
}
?>