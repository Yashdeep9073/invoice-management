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
        SELECT invoice.*, tax.tax_rate, invoice.status AS paymentStatus,
               customer.customer_name, customer.customer_address, customer.customer_phone, customer.customer_email,customer.gst_number,
               COALESCE(customer.ship_name, customer.customer_name) AS ship_name,
               COALESCE(customer.ship_address, customer.customer_address) AS ship_address,
               COALESCE(customer.ship_phone, customer.customer_phone) AS ship_phone,
               COALESCE(customer.ship_email, customer.customer_email) AS ship_email
        FROM invoice 
        INNER JOIN customer ON customer.customer_id = invoice.customer_id
        INNER JOIN tax ON tax.tax_id = invoice.tax
        WHERE invoice_id = ?
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



    // echo "<pre>";
    // print_r($localizationSettings);
    // exit;

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
                $serviceList = '';
                foreach ($serviceIds as $id) {
                    $service = $services[$id] ?? ['name' => 'Unknown Service', 'sac_code' => 'N/A'];
                    $serviceList .= '<li>' . htmlspecialchars($service['name']) . '</li>';
                    $hsnCode = $service['sac_code'];
                }

                // Given values
                $pricePerService = $invoice['quantity'] > 0 ? $invoice['total_amount'] / $invoice['quantity'] : 0;
                $taxRateStr = $invoice['tax_rate']; // "18%"
            
                // Convert tax rate string to integer (remove % and convert to int)
                $taxRate = intval(str_replace('%', '', $taxRateStr)); // Converts "18%" to 18
            
                // Calculate price without tax
                $priceWithoutTax = $taxRate > 0 ? $pricePerService / (1 + $taxRate / 100) : $pricePerService;

                // Calculate tax amount per unit
                $taxAmount = $pricePerService - $priceWithoutTax;
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
    $pdf->SetXY(20, 45);
    $pdf->Cell(22, 10, 'Invoice No: ', 0, 0); // Render "Invoice No:" in black, normal font
    $pdf->SetFont('FuturaBT-Medium', '', 12); // Set font to bold
    $pdf->SetTextColor(0, 101, 156); // Set text color to #3e90ed
    $pdf->Cell(0, 10, $invoice['invoice_number'], 0, 0); // Render invoice number in bold blue
    $pdf->SetTextColor(0, 0, 0); // Reset text color to black
    $pdf->SetFont('FuturaBT-Medium', '', 12); // Reset font to normal
    $pdf->SetXY(150, 45);
    $labelWidth = $pdf->GetStringWidth('Date: ') + 1; // Calculate width of "Date:" with small padding
    $pdf->Cell($labelWidth, 10, 'Date: ', 0, 0); // Render "Date:" in black, normal font with exact width
    $pdf->SetFont('FuturaBT-Medium', '', 12); // Set font to bold
    $pdf->SetTextColor(0, 101, 156); // Set text color to #3e90ed
    $pdf->Cell(0, 10, date('d-M-Y', strtotime($invoice['created_at'])), 0, 1); // Render date in bold blue, no gap
    $pdf->SetTextColor(0, 0, 0); // Reset text color to black
    $pdf->SetFont('FuturaBT-Medium', '', 12); // Reset font to normal

    // Add a horizontal line below the invoice info
    $pdf->SetLineWidth(0.2); // Set line thickness
    $pdf->Line(20, 55, 190, 55); // Draw a horizontal line from (20, 50) to (190, 50)

    // Bill To and Ship To (side by side, below header)
    $pdf->SetFont('FuturaBT-Medium', '', 12);
    $pdf->SetTextColor(0, 101, 156); // Set text color to #3e90ed
    $pdf->SetXY(20, 55);
    $pdf->Cell(90, 10, 'Bill To:', 0, 0);
    $pdf->SetTextColor(0, 0, 0); // Reset text color to black

    $pdf->SetFont('FuturaMdBT-Bold', '', 12);
    $pdf->SetXY(20, 60);
    $pdf->MultiCell(90, 8, $invoice['customer_name'] ?? 'N/A', 0, 'L');

    $address = $invoice['customer_address'] ?? 'N/A';
    $pdf->SetFont('FuturaMdBT-Bold', '', 12);
    $width = $pdf->GetStringWidth($address) + 4; // Add padding
    $pdf->SetXY(20, 65);
    $pdf->MultiCell(max($width, 90), 8, $address, 0, 'L'); // Cap minimum at 90

    $pdf->SetXY(20, 70);
    $pdf->Cell(90, 10, 'Phone: ' . ($invoice['customer_phone'] ?? 'N/A'), 0, 0);


    $pdf->SetXY(20, 75);
    $pdf->Cell(90, 10, 'GST: ' . ($invoice['gst_number'] ?? 'N/A'), 0, 0);
    $pdf->SetLineWidth(0.2); // Set line thickness
    $pdf->Line(20, 85, 190, 85); // Draw a horizontal line from (20, 50) to (190, 50)

    if ($invoiceSettings['is_show_hsn'] === 1) {
        // Bill To and Ship To (side by side, below header)
        $pdf->SetFont('FuturaBT-Medium', '', 12);
        $pdf->SetTextColor(0, 0, 0); // Reset text color to black
        $pdf->SetXY(150, 55);
        $pdf->Cell(90, 10, 'HSN Code:', 0, 0);
        $pdf->SetTextColor(0, 101, 156); // Set text color to #3e90ed
        $pdf->SetXY(172, 55);
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
    $tax = isset($invoice['tax_rate']) ? $invoice['tax_rate'] : 0;
    $finalTotal = $invoice['total_amount'];


    // Start Y position for first line
    $summaryStartY = 170;
    $lineHeight = 6;

    // Line 1: Total
    $pdf->SetTextColor(0, 101, 156); // Set text color to #3e90ed
    $pdf->SetFont('FuturaBT-Medium', '', 12);
    $pdf->SetXY(130, $summaryStartY);
    $formattedAmount = number_format($priceWithoutTax, 2);
    $pdf->Cell(0, $lineHeight, "Total: {$currencySymbol}. {$formattedAmount}/-", 0, 1);    // $pdf->Cell(0, $lineHeight, 'Total:  ₹' . $priceWithoutTax, 0, 1);

    // HR Line below Discount
    $pdf->SetLineWidth(0.2);
    $pdf->Line(130, $summaryStartY + $lineHeight + 0.5, 200, $summaryStartY + $lineHeight + 0.5);

    // Line 2: Discount
    $pdf->SetTextColor(0, 101, 156); // Set text color to #3e90ed
    $pdf->SetFont('FuturaBT-Medium', '', 12);
    $pdf->SetXY(130, $summaryStartY + $lineHeight + 2);
    $pdf->Cell(0, $lineHeight, 'Discount: ' . $discount . "%", 0, 1);

    // HR Line below Discount
    $pdf->SetLineWidth(0.2);
    $pdf->Line(130, $summaryStartY + $lineHeight * 2 + 1.5, 200, $summaryStartY + $lineHeight * 2 + 1.5);

    // Line 3: Tax
    $pdf->SetXY(130, $summaryStartY + $lineHeight + 8);
    $pdf->Cell(0, $lineHeight, 'GST Slab: ' . $tax, 0, 1);

    // HR Line below Tax
    $pdf->Line(130, $summaryStartY + $lineHeight * 3 + 2.5, 200, $summaryStartY + $lineHeight * 3 + 2.5);

    // Line 4: Total Amount
    $pdf->SetXY(130, $summaryStartY + $lineHeight + 16);
    $pdf->SetFont('FuturaMdBT-Bold', '', 12);
    $pdf->Cell(0, $lineHeight, "Total Amount: {$currencySymbol}. " . number_format($finalTotal, 2) . "/-", 0, 1);


    $pdf->Line(130, $summaryStartY + $lineHeight * 4 + 4, 200, $summaryStartY + $lineHeight * 4 + 4);


    // Line 5: Total Amount In Words
    $pdf->SetXY(130, $summaryStartY + $lineHeight + 26);
    $pdf->SetFont('FuturaMdBT-Bold', '', 12);
    $pdf->Cell(0, $lineHeight, 'Total Amount In Words:', 0, 1);

    // HR Line below Tax
    $pdf->Line(130, $summaryStartY + $lineHeight * 6 + 3, 200, $summaryStartY + $lineHeight * 6 + 3);

    $pdf->SetXY(130, $summaryStartY + $lineHeight + 34);
    $pdf->SetTextColor(0, 0, 0); // Reset text color to black
    $pdf->SetFont('FuturaMdBT-Bold', '', 12);

    // Convert number to words
    $amountInWords = numberToWords($finalTotal); // "Six Hundred Six and Fifty Two Paise O"

    // Split into words and group every 3 words
    $words = explode(' ', $amountInWords);
    $groupedWords = array_chunk($words, 4);

    // Output each line
    $currentY = $summaryStartY + $lineHeight + 34;
    foreach ($groupedWords as $wordGroup) {
        $pdf->SetXY(130, $currentY);
        $pdf->Cell(0, $lineHeight, implode(' ', $wordGroup), 0, 1);
        $currentY += $lineHeight; // Move down for next line
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