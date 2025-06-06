<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "./database/config.php";
require 'vendor/autoload.php';

use setasign\Fpdi\Fpdi;

if (isset($_GET['id'])) {
    try {
        // Validate invoice ID
        $invoiceId = intval(base64_decode($_GET['id']));
        if ($invoiceId <= 0) {
            throw new Exception('Invalid invoice ID.');
        }

        // Fetch invoice and customer data
        $stmtFetch = $db->prepare('
            SELECT invoice.*, tax.*,invoice.status as paymentStatus,
                   customer.customer_name, customer.customer_address, customer.customer_phone, customer.customer_email,
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
        if (!$stmtFetch->execute()) {
            throw new Exception('Error fetching invoice: ' . $stmtFetch->error);
        }
        $result = $stmtFetch->get_result();
        $invoice = $result->fetch_assoc(); // Single row
        $stmtFetch->close();

        if (!$invoice) {
            throw new Exception('Invoice not found.');
        }

        // Decode service_id JSON and fetch service names
        $serviceIdJson = $invoice['service_id'] ?? '[]';
        $serviceIds = json_decode($serviceIdJson, true) ?? [];
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error: ' . json_last_error_msg());
        }
        $serviceIds = array_map('intval', $serviceIds);

        // Fetch service names
        $services = [];
        if (!empty($serviceIds)) {
            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            $stmtServices = $db->prepare("SELECT service_id, service_name FROM services WHERE service_id IN ($placeholders)");
            $types = str_repeat('i', count($serviceIds));
            $stmtServices->bind_param($types, ...$serviceIds);
            if (!$stmtServices->execute()) {
                throw new Exception('Error fetching services: ' . $stmtServices->error);
            }
            $result = $stmtServices->get_result();
            while ($row = $result->fetch_assoc()) {
                $services[$row['service_id']] = $row['service_name'];
            }
            $stmtServices->close();
        }

        // Initialize FPDI
        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(true, 20);

        // Import the custom PDF template
        $templatePath = 'public/assets/invoice-temp/invoice-temp-1.pdf';
        if (!file_exists($templatePath)) {
            throw new Exception('Template file not found: ' . $templatePath);
        }
        $pageCount = $pdf->setSourceFile($templatePath);
        $templateId = $pdf->importPage(1);
        $pdf->AddPage();
        $pdf->useTemplate($templateId, 0, 0, 210); // A4 width (210mm)

        // Set font for dynamic text
        $pdf->SetFont('Arial', '', 10);

        // Invoice Info (top-right, adjust based on template)
        $pdf->SetXY(20, 45);
        $pdf->Cell(0, 10, 'Invoice No: ' . $invoice['invoice_number'], 0, 0);
        $pdf->SetXY(150, 45);
        $pdf->Cell(0, 10, 'Date: ' . date('d-M-Y', strtotime($invoice['created_at'])), 0, 1);

        // Add a horizontal line below the invoice info
        $pdf->SetLineWidth(0.2); // Set line thickness
        $pdf->Line(20, 55, 190, 55); // Draw a horizontal line from (20, 50) to (190, 50)

        // Bill To and Ship To (side by side, below header)
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetXY(20, 55);
        $pdf->Cell(90, 10, 'Bill To:', 0, 0);

        $pdf->SetFont('Arial', '', 12);
        $pdf->SetXY(20, 60);
        $pdf->MultiCell(90, 8, $invoice['customer_name'] ?? 'N/A', 0, 'L');

        $pdf->SetXY(20, 65);
        $pdf->MultiCell(90, 8, $invoice['customer_address'] ?? 'N/A', 0, 'L');

        $pdf->SetXY(20, 70);
        $pdf->Cell(90, 10, 'Phone: ' . ($invoice['customer_phone'] ?? 'N/A'), 0, 0);

        $pdf->SetXY(20, 75);
        $pdf->Cell(90, 10, 'Email: ' . ($invoice['customer_email'] ?? 'N/A'), 0, 0);

        $pdf->SetLineWidth(0.2); // Set line thickness
        $pdf->Line(20, 85, 190, 85); // Draw a horizontal line from (20, 50) to (190, 50)

        // Bill To and Ship To (side by side, below header)
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetXY(20, 85);
        $pdf->Cell(90, 10, 'Dear Mam/Sir,', 0, 0);


        // Services Table (middle of page)
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetXY(20, 100);
        $pdf->Cell(80, 10, 'Service', 1);
        $pdf->Cell(30, 10, 'Price', 1);
        $pdf->Cell(30, 10, 'Qty', 1);
        $pdf->Cell(40, 10, 'Total', 1);
        $pdf->Ln();

        // Table Body
        $pdf->SetFont('Arial', '', 12);
        $y = 110;
        $pricePerService = $invoice['quantity'] > 0 ? $invoice['amount'] / $invoice['quantity'] : 0;
        foreach ($serviceIds as $serviceId) {
            $serviceName = $services[$serviceId] ?? 'Unknown Service';
            $pdf->SetXY(20, $y);
            $pdf->Cell(80, 10, $serviceName, 1);
            $pdf->Cell(30, 10, 'Rs. ' . number_format($pricePerService, 2), 1);
            $pdf->Cell(30, 10, 1, 1);
            $pdf->Cell(40, 10, 'Rs. ' . number_format($pricePerService, 2), 1);
            $y += 10;
        }

        // Summary (right-aligned, below table)
        $discount = isset($invoice['discount']) ? $invoice['discount'] : 0;
        $tax = isset($invoice['tax_rate']) ? $invoice['tax_rate'] : 0;
        $finalTotal = $invoice['total_amount'];

        $pdf->SetXY(130, $y + 10);
        $pdf->Cell(35, 10, 'Discount', 1);
        $pdf->Cell(35, 10, $discount . '%', 1);
        $pdf->Ln();

        $pdf->SetXY(130, $y + 20);
        $pdf->Cell(35, 10, 'Tax', 1);
        $pdf->Cell(35, 10, $tax, 1);
        $pdf->Ln();

        $pdf->SetXY(130, $y + 30);
        $pdf->Cell(35, 10, 'Total Amount', 1);
        $pdf->Cell(35, 10, 'Rs. ' . number_format($finalTotal, 2), 1);
        $pdf->Ln();

        // Set font for the thank-you message
        $pdf->SetFont('Arial', '', 10);

        // Position the cursor for the first line
        $pdf->SetXY(20, 135);

        // Write the first line of the message
        $pdf->Cell(0, 10, 'Thank you and looking forward to a', 0, 1); // Move to the next line after writing

        // Add a small vertical gap (optional)
        $pdf->Ln(0); // Adds a 2mm vertical gap

        // Shift the cursor to the right for the second line
        $pdf->SetX(20); // Move the X-coordinate to 30 (10mm to the right of the original 20)

        // Write the second line of the message
        $pdf->Cell(0, 0, 'fruitful relationship with our company', 0, 1);


        // Payment Information (below summary)
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetXY(20, $y + 35);
        $pdf->Cell(0, 1, 'Payment Information', 0, 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->SetXY(20, $y + 35);
        $pdf->Cell(0, 10, 'Payment Method: ' . $invoice['payment_method'], 0, 1);
        $pdf->SetXY(20, $y + 40);
        $pdf->Cell(0, 10, 'Transaction ID: ' . $invoice['transaction_id'], 0, 1);
        $pdf->SetXY(20, $y + 45);
        $pdf->Cell(0, 10, 'Payment Status: ' . $invoice['paymentStatus'], 0, 1);
        $pdf->SetXY(20, $y + 50);
        $pdf->Cell(0, 10, 'Due Date: ' . $invoice['due_date'], 0, 1);

        // Status Stamp (optional, right-aligned)
        $stampY = $y + 100;
        $status = strtoupper($invoice['status']);
        if ($status === 'PAID' && file_exists('public/assets/img/paid_stamp.jpg')) {
            $pdf->Image('public/assets/img/paid_stamp.jpg', 140, $stampY, 50, 50);
        } elseif ($status === 'PENDING' && file_exists('public/assets/img/pending_stamp.png')) {
            $pdf->Image('public/assets/img/pending_stamp.png', 140, $stampY, 50, 50);
        } elseif ($status === 'REFUNDED' && file_exists('public/assets/img/refunded_stamp.jpg')) {
            $pdf->Image('public/assets/img/refunded_stamp.jpg', 140, $stampY, 50, 50);
        } elseif ($status === 'CANCELLED' && file_exists('public/assets/img/cancelled_stamp.png')) {
            $pdf->Image('public/assets/img/cancelled_stamp.png', 140, $stampY, 50, 50);
        }



        // Output the PDF
        $pdf->Output('D', 'Invoice_' . $invoiceId . '.pdf');

    } catch (Exception $e) {
        echo 'Error: ' . htmlspecialchars($e->getMessage());
    }
} else {
    header('Location: index.php');
    exit();
}

ob_end_flush();
?>