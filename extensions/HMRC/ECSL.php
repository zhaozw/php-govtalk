<?php

#
#  ECSL.php
#
#  Created by Jonathon Wardman on 11-07-2010.
#  Copyright 2010, Fubra Limited. All rights reserved.
#
#  This program is free software: you can redistribute it and/or modify
#  it under the terms of the GNU General Public License as published by
#  the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  You may obtain a copy of the License at:
#  http://www.gnu.org/licenses/gpl-3.0.txt
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.

/**
 * HMRC ECSL API client.  Extends the functionality provided by the
 * GovTalk class to build and parse HMRC EC Sales List submissions. The
 * php-govtalk base class needs including externally in order to use this
 * extention.
 *
 * @author Jonathon Wardman
 * @copyright 2010, Fubra Limited
 * @licence http://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License
 */
class HmrcEcsl extends GovTalk {

 /* Magic methods. */

	/**
	 * Instance constructor. Contains a hard-coded HMRC XMLGW URL and additional
	 * schema location.  Adds a channel route identifying the use of this
	 * extension.
	 *
	 * @param string $govTalkSenderId GovTalk sender ID.
	 * @param string $govTalkPassword GovTalk password.
	 * @param string $service The service to use ('tpvs', 'vsips', or 'live').
	 */
	public function __construct($govTalkSenderId, $govTalkPassword, $service = 'live') {

		switch ($service) {
			case 'tpvs':
				parent::__construct('https://www.tpvs.hmrc.gov.uk/HMRC/VATDEC', $govTalkSenderId, $govTalkPassword);
				$this->setTestFlag(true);
			break;
			case 'vsips':
				parent::__construct('https://secure.dev.gateway.gov.uk/submission', $govTalkSenderId, $govTalkPassword);
				$this->setTestFlag(true);
			break;
			default:
				parent::__construct('https://secure.gateway.gov.uk/submission', $govTalkSenderId, $govTalkPassword);
			break;
		}

		$this->setMessageAuthentication('clear');

	}
	
 /* Public methods. */

	/**
	 * Submits an ECSL declaration request.
	 *
	 * The $periodDate array may be in one of two formats, determined by the type
	 * of period this return is for:
	 *   Where $taxPeriod is 'month':
	 *     month => one of 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov' or 'Dec'.
	 *     year => the year, in the format YYYY.
	 *   Where $taxPeriod is 'quarter':
	 *     quarter => one of 1, 2, 3 or 4.
	 *     year => the year, in the format YYYY.
	 *
	 * @param string $vatNumber The VAT number of the company this return is for.
	 * @param string $branchNumber The company's HMRC branch code.
	 * @param string $branchPostcode The company's HMRC branch postcode.
	 * @param string $submitterContact A contact name at the submitting trader organisation for HMRC's use in case they have queries.
	 * @param string $taxPeriod The type of period this this return is for. Valid values are 'month' and 'quarter'.
	 * @param array $periodDate An array containing the date this return is for in one of two formats (see above).
	 * @param array $europeanSales An array containing the details of sales (see above).
	 * @param boolean $strictECSValidation Flag indicating if the default 30% Failed Submission Line Rejection Threshold should be disabled. Defaults to false (no, it shouldn't).
	 * @return mixed An array of 'endpoint', 'interval' and 'correlationid' on success, or false on failure.
	 */
	public function declarationRequest($vatNumber, $branchNumber, $branchPostcode, $submitterContact, $taxPeriod, array $periodDate, array $europeanSales, $strictECSValidation = false) {
	
		$vatNumber = trim(str_replace(' ', '', $vatNumber));
		if (is_numeric($branchNumber) && preg_match('/^(GB)?(\d{9,12})$/', $vatNumber)) { # VAT number
// Postcode validation
			$this->addMessageKey('BranchNo', $branchNumber);
			$this->addMessageKey('Postcode', $branchPostcode);
			$this->addMessageKey('VATRegNo', $vatNumber);
			
			if ((($taxPeriod == 'month') || ($taxPeriod == 'quarter')) && (isset($periodDate['year']) && preg_match('/^\d{4}$/', $periodDate['year']))) { # Tax period
			
	 // Set the message envelope bits and pieces for this request...
				$this->setMessageClass('HMCE-ECSL-ORG-V101');
				$this->setMessageQualifier('request');
				$this->setMessageFunction('submit');

	 // Build message body...
				$package = new XMLWriter();
				$package->openMemory();
				$package->setIndent(true);
				$package->startElement('EuropeanSalesDeclarationRequest');
					$package->writeAttribute('xsi:schemaLocation', 'http://www.govtalk.gov.uk/taxation/vat/europeansalesdeclaration/1 EuropeanSalesDeclarationRequest.xsd');
					$package->writeAttribute('SchemaVersion', '1.0');
					
					$package->startElement('Header');
						$package->writeElement('VATCore:SubmittersContactName', $submitterContact);
						$package->writeElement('VATCore:CurrencyCode', 'GBP');
						if ($taxPeriod == 'month') {
							$validMonths = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
							                     'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
							if (isset($periodDates['month']) && in_array($periodDate['month'], $validMonths)) {
								$package->startElement('VATCore:TaxMonthlyPeriod');
									$package->writeElement('VATCore:TaxMonth', $periodDate['month']);
									$package->writeElement('VATCore:TaxMonthPeriodYear', $periodDate['year']);
								$package->endElement(); # VATCore:TaxMonthlyPeriod
							} else {
								return false;
							}
						} else if ($taxPeriod == 'quarter') {
							$validQuarters = array(1, 2, 3, 4);
							if (isset($periodDates['quarter']) && in_array($periodDate['quarter'], $validQuarters)) {
								$package->startElement('VATCore:TaxQuarter');
									$package->writeElement('VATCore:TaxQuarterNumber', $periodDate['quarter']);
									$package->writeElement('VATCore:TaxQuarterYear', $periodDate['year']);
								$package->endElement(); # VATCore:TaxQuarter
							} else {
								return false;
							}
						}
						if ($strictECSValidation === true) {
							$package->writeElement('VATCore:ApplyStrictEuropeanSaleValidation', true);
						}
					$package->endElement(); # Header
					
					
					
					
							
							$package->startElement('Body');
								$package->writeElement('VATCore:VATDueOnOutputs', sprintf('%.2f', $vatOutput));
								$package->writeElement('VATCore:VATDueOnECAcquisitions', sprintf('%.2f', $vatECAcq));
								if ($totalVat === null) {
									$totalVat = $vatOutput + $vatECAcq;
								}
								$package->writeElement('VATCore:TotalVAT', sprintf('%.2f', $totalVat));
								$package->writeElement('VATCore:VATReclaimedOnInputs', sprintf('%.2f', $vatReclaimedInput));
								if ($netVat === null) {
									$netVat = abs($totalVat - $vatReclaimedInput);
								}
								if ($netVat < 0) {
								   return false;
								}
								$package->writeElement('VATCore:NetVAT', sprintf('%.2f', $netVat));
								$package->writeElement('VATCore:NetSalesAndOutputs', floor($netOutput));
								$package->writeElement('VATCore:NetPurchasesAndInputs', floor($netInput));
								$package->writeElement('VATCore:NetECSupplies', floor($netECSupply));
								$package->writeElement('VATCore:NetECAcquisitions', floor($netECAcq));
							$package->endElement(); # Body
						$package->endElement(); # VATDeclarationRequest

	 // Send the message and deal with the response...
						$this->setMessageBody($package);
						$this->addChannelRoute('http://blogs.fubra.com/php-govtalk/extensions/hmrc/vat/', 'php-govtalk HMRC VAT1 extension', '0.1.1');
						if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
							$returnable = $this->getResponseEndpoint();
							$returnable['correlationid'] = $this->getResponseCorrelationId();
							return $returnable;
						} else {
							return false;
						}

			} else {
				return false;
			}
		} else {
			return false;
		}

	}

}