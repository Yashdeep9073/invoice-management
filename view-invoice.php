<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require "./database/config.php";
require 'vendor/autoload.php';
require_once __DIR__ . "/utility/numberToWords.php";

use setasign\Fpdi\Fpdi;
use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

try {

    // Decode Invoice ID
    $invoiceId = intval(base64_decode($_GET['id']));
    if ($invoiceId <= 0)
        throw new Exception("Invalid invoice ID.");

    // Fetch invoice data
    $stmtFetch = $db->prepare('
    SELECT 
        invoice.*,
        invoice.status AS paymentStatus,

        customer.customer_name,
        customer.customer_address,
        customer.customer_phone,
        customer.customer_email,
        customer.gst_number,

        COALESCE(customer.ship_name, customer.customer_name) AS ship_name,
        COALESCE(customer.ship_address, customer.customer_address) AS ship_address,
        COALESCE(customer.ship_phone, customer.customer_phone) AS ship_phone,
        COALESCE(customer.ship_email, customer.customer_email) AS ship_email,

        GROUP_CONCAT(tax.tax_name) AS tax_names,
        GROUP_CONCAT(tax.tax_rate) AS tax_rates

    FROM invoice 

    INNER JOIN customer 
        ON customer.customer_id = invoice.customer_id

    LEFT JOIN invoice_tax it 
        ON it.invoice_id = invoice.invoice_id

    LEFT JOIN tax 
        ON tax.tax_id = it.tax_id

    WHERE invoice.invoice_id = ?

    GROUP BY invoice.invoice_id
');
    $stmtFetch->bind_param('i', $invoiceId);
    $stmtFetch->execute();
    $result = $stmtFetch->get_result();
    $invoice = $result->fetch_assoc();


    if (!$invoice)
        throw new Exception("Invoice not found.");

    // Decode service IDs
    $serviceIds = json_decode($invoice['service_id'] ?? '[]', true) ?? [];
    $serviceIds = array_map('intval', $serviceIds);

    // Fetch service names
    $services = [];
    $hsnCode;
    if (!empty($serviceIds)) {
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $types = str_repeat('i', count($serviceIds));
        $stmtServices = $db->prepare("SELECT service_id, service_name,sac_code FROM services WHERE service_id IN ($placeholders)");
        $stmtServices->bind_param($types, ...$serviceIds);
        $stmtServices->execute();
        $res = $stmtServices->get_result();
        while ($row = $res->fetch_assoc()) {
            $services[$row['service_id']] = [
                'name' => $row['service_name'],
                'sac_code' => $row['sac_code']
            ];
        }
    }

    $stmtFetch = $db->prepare("SELECT * FROM invoice_settings");
    $stmtFetch->execute();
    $invoiceSettings = $stmtFetch->get_result()->fetch_array(MYSQLI_ASSOC);

    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id WHERE currency.is_active = 1 ;");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);
    $currencySymbol = $localizationSettings["currency_code"] ?? "$";



    $quantity = max(1, (int) ($invoice['quantity'] ?? 1));
    $baseAmount = (float) ($invoice['amount'] ?? 0);
    $discountPercent = (float) ($invoice['discount'] ?? 0);

    $subtotal = $baseAmount * $quantity;
    $discountAmount = ($subtotal * $discountPercent) / 100;
    $taxableAmount = $subtotal - $discountAmount;

    $taxNames = array_map('trim', explode(',', (string) ($invoice['tax_names'] ?? '')));
    $taxRates = array_map('trim', explode(',', (string) ($invoice['tax_rates'] ?? '')));

    $totalTaxAmount = 0;
    $taxDetails = [];
    $totalTaxRate = 0;

    foreach ($taxRates as $i => $rateStr) {

        $rate = (float) str_replace('%', '', $rateStr);
        if ($rate <= 0) {
            continue;
        }

        $name = trim($taxNames[$i] ?? 'GST');

        $taxAmount = ($taxableAmount * $rate) / 100;

        $totalTaxAmount += $taxAmount;
        $totalTaxRate += $rate;

        $taxDetails[] = [
            'name' => $name,
            'rate' => $rate,
            'amount' => $taxAmount
        ];
    }

    $finalTotal = (float) ($invoice['total_amount'] ?? ($taxableAmount + $totalTaxAmount));


    // Step 1: Generate stylized Service Table using DomPDF
    ob_start();
    ?>
    <style>
        body {
            /* font-family: 'Montserrat'; */
            margin: 0;
            padding: 20px;
        }

        .invoice-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 20px 0;
            border: 1px solid #4e4e4eff;

        }

        .invoice-table th {
            background-color: rgb(7, 86, 155);
            color: white;
            padding: 8px 10px;
            text-align: center;
            font-weight: 600;
            border: none;
        }

        .invoice-table td {
            padding: 50px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #e0e0e0;
            font-weight: 600;
        }

        .invoice-table td.services-cell {
            text-align: left;
            padding-left: 20px;
        }

        .invoice-table .services-list {
            white-space: nowrap;
        }

        .invoice-table tr:nth-child(even) {
            background-color: #f5f5f5;
        }

        .invoice-table tr:hover {
            background-color: #f0f7ff;
        }

        .amount-cell {
            font-weight: 600;
            color: #00659c;
        }

        .table-container {
            border-radius: 2px;
            overflow: hidden;
        }

        .table-title {
            text-align: center;
            margin-bottom: 15px;
            color: #333;
            font-size: 1.4em;
            font-weight: 600;
        }
    </style>

    <div class="table-container">
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>DESCRIPTION</th>
                    <th>SERVICES</th>
                    <th>TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Build the list of service names
                $hsnCode = 'N/A';
                foreach ($serviceIds as $id) {
                    $service = $services[$id] ?? ['name' => 'Unknown Service', 'sac_code' => 'N/A'];
                    $hsnCode = $service['sac_code'];
                }

                $pricePerService = $quantity > 0 ? $finalTotal / $quantity : 0;
                $priceWithoutTax = $totalTaxRate > 0
                    ? $pricePerService / (1 + $totalTaxRate / 100)
                    : $pricePerService;
                ?>
                <tr>
                    <td class="amount-cell"> <?= isset($invoice['invoice_title']) ? $invoice['invoice_title'] : "" ?></td>
                    <td class="services-cell">
                        <div class="services-list">
                            <?php $counter = 1;
                            foreach ($serviceIds as $id): ?>
                                <?= $counter . "." . htmlspecialchars($services[$id]['name'] ?? 'Unknown Service') ?><br>
                                <?php $counter++; endforeach; ?>
                        </div>
                    </td>
                    <td class="amount-cell">
                        <?= $currencySymbol . ". " . number_format($priceWithoutTax, 2) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
    $html = ob_get_clean();

    $dompdf = new Dompdf(['isRemoteEnabled' => true]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $tempPdfPath = sys_get_temp_dir() . '/service_table.pdf';
    file_put_contents($tempPdfPath, $dompdf->output());

    // Step 2: Overlay on main invoice PDF using FPDI
    $pdf = new Fpdi();
    $templatePath = isset($invoiceSettings['template']) ? $invoiceSettings['template'] : 'public/assets/invoice-temp/invoice-temp-1.pdf';

    if (!file_exists($templatePath)) {
        throw new Exception("Template not found.");
    }

    // Import main template
    $pageCount = $pdf->setSourceFile($templatePath);
    $templateId = $pdf->importPage(1);
    $pdf->AddPage();
    // Register the font
    $pdf->AddFont('montserratb', '', 'Montserrat-Bold.php');
    $pdf->AddFont('FuturaMdBT-Bold', '', 'futuramdbt_bold.php');
    $pdf->AddFont('FuturaBT-Medium', '', 'Futura Md Bt Medium.php');
    $pdf->useTemplate($templateId, 0, 0, 210);

    // Import DomPDF-generated table
    $pdf->setSourceFile($tempPdfPath);
    $tableId = $pdf->importPage(1);
    $pdf->useTemplate($tableId, 5, 80, 200); // Adjust X,Y,width as needed

    // Invoice Info (top-right, adjust based on template)
    $pdf->SetFont('FuturaBT-Medium', '', 12); // Set font to normal Times
    $pdf->SetTextColor(0, 0, 0); // Set text color to black
    $pdf->SetXY(20, 40);
    $pdf->Cell(22, 10, 'Invoice No: ', 0, 0); // Render "Invoice No:" in black, normal font
    $pdf->SetFont('FuturaBT-Medium', '', 12); // Set font to bold
    $pdf->SetTextColor(0, 101, 156); // Set text color to #3e90ed
    $pdf->Cell(0, 10, $invoice['invoice_number'], 0, 0); // Render invoice number in bold blue
    $pdf->SetTextColor(0, 0, 0); // Reset text color to black
    $pdf->SetFont('FuturaBT-Medium', '', 12); // Reset font to normal
    $pdf->SetXY(140, 40);
    $labelWidth = $pdf->GetStringWidth('Date: ') + 1; // Calculate width of "Date:" with small padding
    $pdf->Cell($labelWidth, 10, 'Date: ', 0, 0); // Render "Date:" in black, normal font with exact width
    $pdf->SetFont('FuturaBT-Medium', '', 12); // Set font to bold
    $pdf->SetTextColor(0, 101, 156); // Set text color to #3e90ed
    $pdf->Cell(0, 10, date('d-M-Y', strtotime($invoice['created_at'])), 0, 1); // Render date in bold blue, no gap
    $pdf->SetTextColor(0, 0, 0); // Reset text color to black
    $pdf->SetFont('FuturaBT-Medium', '', 12); // Reset font to normal

    // Add a horizontal line below the invoice info
    $pdf->SetLineWidth(0.2); // Set line thickness
    $pdf->Line(20, 50, 190, 50); // Draw a horizontal line from (20, 50) to (190, 50)

    // Bill To and Ship To (side by side, below header)
    $pdf->SetFont('FuturaBT-Medium', '', 12);
    $pdf->SetXY(20, 50);
    $pdf->Cell(90, 10, 'Bill To:', 0, 0);
    $pdf->SetTextColor(0, 0, 0); // Reset text color to black

    $pdf->SetFont('FuturaMdBT-Bold', '', 12);
    $pdf->SetXY(20, 55);
    $pdf->MultiCell(90, 8, $invoice['customer_name'] ?? 'N/A', 0, 'L');

    function cleanAddress($text)
    {
        $text = trim($text);
        // 35 chars per line works nicely for 90mm width
        return wordwrap($text, 35, "\n", true);
    }


    $address = cleanAddress($invoice['customer_address'] ?? 'N/A');
    $pdf->SetFont('FuturaMdBT-Bold', '', 10);
    $pdf->SetXY(20, 60);
    $pdf->MultiCell(120, 5, $address, 0, 'L');


    // Bill To and Ship To (side by side, below header)
    // $pdf->SetFont('FuturaBT-Medium', '', 12);
    // $pdf->SetXY(140, 55);
    // $pdf->Cell(90, 10, 'Ship To:', 0, 0);
    // $pdf->SetTextColor(0, 0, 0); // Reset text color to black


    // $pdf->SetFont('FuturaMdBT-Bold', '', 12);
    // $pdf->SetXY(140, 61);
    // $pdf->MultiCell(90, 8, $invoice['ship_name'] ?? 'N/A', 0, 'L');
    // $address = cleanAddress($invoice['ship_address'] ?? 'N/A');
    // $pdf->SetFont('FuturaMdBT-Bold', '', 10);
    // $pdf->SetXY(140, 66);
    // $pdf->MultiCell(120, 5, $address, 0, 'L');


    $pdf->SetFont('FuturaMdBT-Bold', '', 10);
    $pdf->SetXY(20, 72);
    $pdf->Cell(90, 10, 'GST: ' . ($invoice['gst_number'] ?? 'N/A'), 0, 0);
    $pdf->SetLineWidth(0.2); // Set line thickness
    $pdf->Line(20, 85, 190, 85); // Draw a horizontal line from (20, 50) to (190, 50)

    if ($invoiceSettings['is_show_hsn'] === 1) {
        // Bill To and Ship To (side by side, below header)
        $pdf->SetFont('FuturaBT-Medium', '', 12);
        $pdf->SetTextColor(0, 0, 0); // Reset text color to black
        $pdf->SetXY(140, 50);
        $pdf->Cell(90, 10, 'HSN Code:', 0, 0);
        $pdf->SetTextColor(0, 101, 156); // Set text color to #3e90ed
        $pdf->SetXY(162, 50);
        $pdf->SetFont('FuturaBT-Medium', '', 12);
        $pdf->Cell(90, 10, $hsnCode, 0, 0);
        $pdf->SetTextColor(0, 0, 0); // Reset text color to black
    }

    if ($invoiceSettings['is_show_bill_date'] === 1 && $invoice['invoice_type'] == "RECURSIVE") {
        // Bill To and Ship To (side by side, below header)
        $pdf->SetFont('FuturaBT-Medium', '', 12);
        $pdf->SetTextColor(0, 0, 0); // Reset text color to black
        $pdf->SetXY(115, 85);
        $pdf->Cell(15, 10, 'Bill Duration:', 0, 0); // Narrower cell for label
        $pdf->SetTextColor(0, 101, 156); // Set text color to #3e90ed
        $pdf->SetXY(142, 85);
        $pdf->SetFont('FuturaBT-Medium', '', 12);
        $fromDate = date('M d', strtotime(trim($invoice['from_date'])));
        $toDate = date('M d, Y', strtotime(trim($invoice['to_date'])));
        $pdf->Cell(90, 10, $fromDate . ' To ' . $toDate, 0, 0);
        $pdf->SetTextColor(0, 0, 0); // Reset text color to black

    }

    // Bill To and Ship To (side by side, below header)
    $pdf->SetFont('FuturaBT-Medium', '', 12);
    $pdf->SetXY(20, 85);
    $pdf->Cell(90, 10, 'Kind attention!', 0, 0);
    // Bill To and Ship To (side by side, below header)
    $pdf->SetFont('FuturaBT-Medium', '', 12);
    $pdf->SetXY(20, 90);
    $pdf->Cell(90, 10, 'Dear Sir/Madam,', 0, 0);

    // Summary (center-aligned, below table)
    $discount = isset($invoice['discount']) ? $invoice['discount'] : 0;


    // Start Y position for first line
    $summaryStartY = 160;
    $lineHeight = 6;
    $rowGap = 2;
    $rightX = 130;
    $rightLineEnd = 200;
    $currentY = $summaryStartY;

    $pdf->SetTextColor(0, 101, 156);
    $pdf->SetFont('FuturaBT-Medium', '', 12);

    $formattedAmount = number_format($priceWithoutTax, 2);
    $pdf->SetXY($rightX, $currentY);
    $pdf->Cell(0, $lineHeight, "Sub Total: {$currencySymbol}. {$formattedAmount}/-", 0, 1);
    $currentY += $lineHeight;
    $pdf->Line($rightX, $currentY + 0.5, $rightLineEnd, $currentY + 0.5);
    $currentY += $rowGap;

    $pdf->SetXY($rightX, $currentY);
    $pdf->Cell(0, $lineHeight, 'Discount: ' . $discount . "%", 0, 1);
    $currentY += $lineHeight;
    $pdf->Line($rightX, $currentY + 0.5, $rightLineEnd, $currentY + 0.5);
    $currentY += $rowGap;

    foreach ($taxDetails as $tax) {
        $pdf->SetXY($rightX, $currentY);
        $pdf->Cell(
            0,
            $lineHeight,
            "{$tax['name']} ({$tax['rate']}%): {$currencySymbol}. " . number_format($tax['amount'], 2),
            0,
            1
        );
        $currentY += $lineHeight;
    }

    $pdf->SetFont('FuturaMdBT-Bold', '', 12);
    $currentY += 2;
    $pdf->SetXY($rightX, $currentY);
    $pdf->Cell(0, $lineHeight, "Total Amount: {$currencySymbol}. " . number_format($finalTotal, 2) . "/-", 0, 1);
    $currentY += $lineHeight;
    $pdf->Line($rightX, $currentY + 0.5, $rightLineEnd, $currentY + 0.5);

    $currentY += 4;
    $pdf->SetXY($rightX, $currentY);
    $pdf->Cell(0, $lineHeight, 'Total Amount In Words:', 0, 1);
    $currentY += $lineHeight;
    $pdf->Line($rightX, $currentY + 0.5, $rightLineEnd, $currentY + 0.5);

    $currentY += 3;
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('FuturaMdBT-Bold', '', 12);

    $amountInWords = numberToWords($finalTotal);
    $words = explode(' ', $amountInWords);
    $groupedWords = array_chunk($words, 4);

    foreach ($groupedWords as $wordGroup) {
        $pdf->SetXY($rightX, $currentY);
        $pdf->Cell(0, $lineHeight, implode(' ', $wordGroup), 0, 1);
        $currentY += $lineHeight;
    }

    // Set font for the thank-you message
    $pdf->SetFont('FuturaBT-Medium', '', 10);
    $pdf->SetTextColor(0, 0, 0); // Reset text color to black

    // Position the cursor for the first line
    $pdf->SetXY(20, $summaryStartY);

    // Write the first line of the message
    $pdf->Cell(0, 10, 'Thank you and looking forward to a', 0, 1); // Move to the next line after writing

    // Add a small vertical gap (optional)
    $pdf->Ln(0); // Adds a 2mm vertical gap

    // Shift the cursor to the right for the second line
    $pdf->SetX(20); // Move the X-coordinate to 30 (10mm to the right of the original 20)

    // Write the second line of the message
    $pdf->Cell(0, 0, 'fruitful relationship with our company.', 0, 1);

    // Add paid stamp if invoice is paid
    if ($invoice['status'] == "PAID") {
        $stampPath = !empty($invoiceSettings['invoice_stamp_url']) ? $invoiceSettings['invoice_stamp_url'] : 'public/assets/stamp/paid_stamp.png';
        if (file_exists($stampPath)) {
            // Medium stamp (30x30 pixels) - recommended
            $pdf->Image($stampPath, 140, 220, 45, 30);
        }
    }
    // Add paid stamp if invoice is PENDING 
    if ($invoice['status'] == "PENDING") {
        $stampPath = 'public/assets/stamp/pending_stamp.png';
        if (file_exists($stampPath)) {
            // Medium stamp (30x30 pixels) - recommended
            $pdf->Image($stampPath, 130, 220, 50, 20);
        }
    }
    // Add paid stamp if invoice is REFUNDED
    if ($invoice['status'] == "REFUNDED") {
        $stampPath = 'public/assets/stamp/refund_stamp.png';
        if (file_exists($stampPath)) {
            // Medium stamp (30x30 pixels) - recommended
            $pdf->Image($stampPath, 80, 110, 100, 100);
        }
    }
    // Add paid stamp if invoice is paid
    if ($invoice['status'] == "CANCELLED") {
        $stampPath = 'public/assets/stamp/cancel_stamp.png';
        if (file_exists($stampPath)) {
            // Medium stamp (30x30 pixels) - recommended
            $pdf->Image($stampPath, 80, 150, 80, 35);
        }
    }

    // // Payment Information Section
    // $pdf->SetFont('Helvetica', 'B', 12);
    // $pdf->SetXY(20, 160);
    // $pdf->SetTextColor(0, 101, 156); // Blue (#3e90ed)
    // $pdf->Cell(0, 5, 'Payment Information:', 0, 1);
    // $pdf->SetTextColor(0, 0, 0); // Black

    // // Payment Method
    // $pdf->SetXY(20, 165);
    // $pdf->SetFont('Helvetica', '', 12); // Regular font for label
    // $pdf->SetTextColor(0, 0, 0); // Black for label
    // $pdf->Write(5, 'Payment Method: ');
    // $pdf->SetFont('Helvetica', 'B', 12); // Bold font for value
    // $pdf->SetTextColor(0, 101, 156); // Blue for value
    // $pdf->Write(5, $invoice['payment_method'] . "\n");

    // // Transaction ID
    // $pdf->SetXY(20, 170);
    // $pdf->SetFont('Helvetica', '', 12); // Regular font for label
    // $pdf->SetTextColor(0, 0, 0); // Black for label
    // $pdf->Write(5, 'Transaction ID: ');
    // $pdf->SetFont('Helvetica', 'B', 12); // Bold font for value
    // $pdf->SetTextColor(0, 101, 156); // Blue for value
    // $pdf->Write(5, $invoice['transaction_id'] . "\n");

    // // Payment Status
    // $pdf->SetXY(20, 175);
    // $pdf->SetFont('Helvetica', '', 12); // Regular font for label
    // $pdf->SetTextColor(0, 0, 0); // Black for label
    // $pdf->Write(5, 'Payment Status: ');
    // $pdf->SetFont('Helvetica', 'B', 12); // Bold font for value
    // $pdf->SetTextColor(0, 101, 156); // Blue for value
    // $pdf->Write(5, $invoice['paymentStatus'] . "\n");

    // // Due Date
    // $pdf->SetXY(20, 180);
    // $pdf->SetFont('Helvetica', '', 12); // Regular font for label
    // $pdf->SetTextColor(0, 0, 0); // Black for label
    // $pdf->Write(5, 'Due Date: ');
    // $pdf->SetFont('Helvetica', 'B', 12); // Bold font for value
    // $pdf->SetTextColor(0, 101, 156); // Blue for value
    // $pdf->Write(5, $invoice['due_date'] . "\n");

    // Output final PDF
    $pdf->Output("I", "Final_Invoice_{$invoiceId}.pdf");


} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}