<?php
include("common_functions.php");
$resourceID = database_connect();
$inputs = sanitize_inputs($_REQUEST);
// Include the main TCPDF library (search for installation path).
require_once('tcpdf/tcpdf.php');
// create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, 'in', array(8.1, 11), true, 'UTF-8', true);
// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('PSUG Events LLC');
$pdf->SetTitle('Invoice');
// set header and footer fonts
//$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
// set margins
//$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
//$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
// set font
$pdf->SetFont('helvetica', '', 10);
$pdf->setHeaderData('', 0, '', '', array(0,0,0), array(255,255,255));
$pdf->setFooterData('', 0, '', '', array(0,0,0), array(255,255,255));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$confirmations = array();
foreach (explode(",", $inputs['confirmation']) as $confirmation){
	$confirmations[] = "'$confirmation'";
}
$confirmations = implode(",", $confirmations);
$query = "
	SELECT
		registrations.*,
		format(ROUND(registrations.registration_price_num,2) - ROUND(registrations.payment_total_num,2),2) balance
	FROM
		(
			SELECT
				accounts.id accountid,
				accounts.fax,
				accounts.phone,
				accounts.email,
				concat(events.city, ', ', events.state) location,
				registrations.id registrationid,
				attendees.last_name,
				attendees.first_name,
				attendees.business,
				attendees.address1,
				attendees.address2,
				attendees.city,
				attendees.state,
				attendees.zip,
				registrations.payment_method,
				registrations.confirmation,
				registrations.payment_number,
				events.name event,
				events.logo,
				DATE_FORMAT(events.startdate, '%M %d, %Y') startdate,
				DATE_FORMAT(events.enddate, '%M %d, %Y') enddate,
				events.site,
				events.checkinstructions,
				registration_types.name registration_type,
				CASE 
					WHEN account_magicwrighter_info.enable_online_pymts = 1 THEN 1
					WHEN preferences.value = 1 THEN 1
					ELSE 0
				END AS showCC,
				format(registration_types.price  + COALESCE((
						SELECT SUM(registration_extras.price * COALESCE(registration_extra_orders.quantity,0))
						FROM registration_extra_orders
						JOIN registration_extras ON registration_extras.id = registration_extra_orders.registration_extras_id
						WHERE registration_extra_orders.registrationid = registrations.id
					) ,0),2) registration_price,
				registration_types.price  + COALESCE((
						SELECT SUM(registration_extras.price * COALESCE(registration_extra_orders.quantity,0))
						FROM registration_extra_orders
						JOIN registration_extras ON registration_extras.id = registration_extra_orders.registration_extras_id
						WHERE registration_extra_orders.registrationid = registrations.id
					) ,0) registration_price_num,
				format(coalesce((
					SELECT sum(amount) total
					FROM registration_payments
					WHERE registrations.id = registration_payments.registrationid
				), 0),2) payment_total,
				coalesce((
					SELECT sum(amount) total
					FROM registration_payments
					WHERE registrations.id = registration_payments.registrationid
				), 0) payment_total_num
			FROM
				registrations
				JOIN events on registrations.eventid = events.id
				JOIN registration_types on registrations.registration_typeid = registration_types.id
				JOIN accounts on events.accountid = accounts.id
				LEFT JOIN attendees ON attendees.id = registrations.attendeeid 
				LEFT JOIN account_magicwrighter_info ON account_magicwrighter_info.accountid = accounts.id
				LEFT JOIN preferences ON preferences.accountid = accounts.id
					AND preferences.name = 'basysEnabled'
			WHERE
				registrations.confirmation in ($confirmations)
		) registrations
		ORDER BY last_name,	first_name
	";

$resultID = mysqli_query($resourceID, $query);

while ($registration = mysqli_fetch_assoc($resultID)){
	$pdf->AddPage();
	$pdf->setCellPaddings(0, 0, 0, 0);
	$pdf->setCellMargins(0, 0, 0, 0);

	$pdf->Image("img/{$registration['logo']}.png", .25, .25, 4, .8, 'PNG', false, '', true, 150, '', false, false, 0, false, false, false);
	$pdf->SetFont('helvetica', 'B', 18);
	$pdf->MultiCell(2, .25, "INVOICE", 0, 'R', false, 0, 5.75, .25);

	$pdf->SetFont('helvetica', '', 10);

	$today = date("m/d/Y");

	$pdf->MultiCell(2, .25, "Invoice Number: {$registration['confirmation']}\nInvoice Date: $today", 0, 'R', false, 0, 5.75, .75);
	if (strtoupper($registration['payment_method']) == "PO" and $registration['payment_number'] != "")
	{
		$pdf->MultiCell(2, .25, "PO Number: {$registration['payment_number']}", 0, 'R', false, 0, 5.75, 1.25);
	}

	$pdf->MultiCell(4, 1, "Fax: {$registration['fax']}\nPhone: {$registration['phone']}\nE-mail: {$registration['email']}", 0, 'L', false, 0, 0.5, 1.0);

	$pdf->MultiCell(4, 1, "{$registration['event']}\n{$registration['site']}\n{$registration['location']}\n{$registration['startdate']} - {$registration['enddate']}", 0, 'L', false, 0, 0.5, 1.75);

	$fields = json_decode("[". $registration['data']. "]", true);

	$address = "{$registration['first_name']} {$registration['last_name']}\n{$registration['business']}\n{$registration['address1']}";
	if ($registration['address2'] <> "") $address .= "\n{$registration['address2']}";
	$address .= "\n{$registration['city']}, {$registration['state']} {$registration['zip']}\n\nATTN: Accounts Payable";

	$pdf->MultiCell(4, 2, $address, 0, 'L', false, 0, 7/8, 2.75);

	$pdf->MultiCell(4, 1, "Registration Type: ". $registration['registration_type'], 0, 'L', false, 0, 0.5, 4.5);

	$pdf->SetFont('courier', '', 10);
	$pdf->MultiCell(4, 1,
		"Registration Fee: $". str_pad($registration['registration_price'], 8, " ", STR_PAD_LEFT).
		"\nPayments Made: $". str_pad($registration['payment_total'], 8, " ", STR_PAD_LEFT).
		"\nBalance Remaining: $". str_pad($registration['balance'], 8, " ", STR_PAD_LEFT),
	0, 'R', false, 0, 3.5, 4.5);
	$pdf->SetFont('helvetica', '', 10);
	if($registration['showCC'] == '1'){
		$paymentfooter = "To pay by credit card visit: https://". $_SERVER['HTTP_HOST'] . "/payRegistration.php?rid=" . $registration['registrationid'];
		$pdf->MultiCell(6, 1, $paymentfooter, 0, 'L', false, 0, 0.5, 5.5);
	}
	$paymentfooter = "To pay by check:\n\n". $registration['checkinstructions'];
	$pdf->MultiCell(4, 1, $paymentfooter, 0, 'L', false, 0, 0.5, 6.5);
}
// move pointer to last page
$pdf->lastPage();
//Close and output PDF document
$pdf->Output('invoice.pdf', 'I');
mysqli_close($resourceID);