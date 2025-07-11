<?php
session_start();

require './vendor/autoload.php';
require './database/config.php';
require './utility/env.php';

try {

    function generateInvoiceNumber($db)
    {
        // Get current date in YYYYMMDD format
        $date = date('Ymd'); // e.g., '20250530'

        // Use a transaction to ensure atomicity
        try {
            $db->begin_transaction();

            $stmtFetchInvoiceSettings = $db->prepare("SELECT * FROM invoice_settings");
            $stmtFetchInvoiceSettings->execute();
            $invoiceSettings = $stmtFetchInvoiceSettings->get_result()->fetch_array(MYSQLI_ASSOC);
            $prefix = isset($invoiceSettings['invoice_prefix']) ? $invoiceSettings['invoice_prefix'] : "VIS";

            // Lock the row for the current date
            $stmt = $db->prepare("SELECT last_sequence FROM invoice_sequence WHERE date = ? FOR UPDATE");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                // No sequence for today, create one
                $stmt = $db->prepare("INSERT INTO invoice_sequence (date, last_sequence) VALUES (?, 0)");
                $stmt->bind_param("s", $date);
                $stmt->execute();
                $lastSequence = 0;
            } else {
                $row = $result->fetch_assoc();
                $lastSequence = $row['last_sequence'];
            }

            // Increment sequence
            $newSequence = $lastSequence + 1;

            // Update sequence
            $stmt = $db->prepare("UPDATE invoice_sequence SET last_sequence = ? WHERE date = ?");
            $stmt->bind_param("is", $newSequence, $date);
            $stmt->execute();

            $db->commit();

            // Format invoice number
            return $invoiceNumber = sprintf("$prefix-%s-%05d", $date, $newSequence); // e.g., VIS-20250530-00001

        } catch (Exception $e) {
            $db->rollback();
            return [
                "status" => 500,
                "error" => $e->getMessage()
            ];
        }
    }

    function strToNumber($string)
    {
        return intval(str_replace('%', '', $string));
    }

    $stmtFetchInvoices = $db->prepare("SELECT 
                invoice.*,
                invoice.status as invoiceStatus,
                customer.customer_id,
                customer.customer_name,
                admin.admin_username,
                tax.tax_rate
                FROM invoice 
                INNER JOIN customer
                ON customer.customer_id = invoice.customer_id
                LEFT JOIN admin
                ON admin.admin_id = invoice.created_by 
                  INNER JOIN tax ON tax.tax_id = invoice.tax
                WHERE invoice.is_active = 1 AND invoice_type = 'RECURSIVE'
                ");
    if ($stmtFetchInvoices->execute()) {
        $invoices = $stmtFetchInvoices->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        echo 'Error for fetching customers';
    }

    $stmtFetchLocalizationSettings = $db->prepare("SELECT * FROM localization_settings INNER JOIN currency ON localization_settings.currency_id = currency.currency_id;");
    $stmtFetchLocalizationSettings->execute();
    $localizationSettings = $stmtFetchLocalizationSettings->get_result()->fetch_array(MYSQLI_ASSOC);
    $timezone = $localizationSettings["timezone"] ?? "UTC";

    // Current date for comparison
    $currentDate = new DateTime('now', new DateTimeZone($timezone)); // Adjust timezone as needed




    foreach ($invoices as $invoice) {

        if (empty($invoice['created_at']) || !strtotime($invoice['created_at'])) {
            continue;
        }

        $createdAt = new DateTime($invoice['created_at'], new DateTimeZone($timezone));
        $repeatCycle = $invoice['repeat_cycle'];
        $createBefore = (int) $invoice['create_before'];

        $interval = null;
        switch ($repeatCycle) {
            case 'DAILY':
                $interval = new DateInterval('P1D');
                break;
            case 'WEEKLY':
                $interval = new DateInterval('P7D');
                break;
            case 'MONTHLY':
                $interval = new DateInterval('P1M');
                break;
            case 'QUARTERLY':
                $interval = new DateInterval('P3M');
                break;
            case 'SEMIQUARTERLY':
                $interval = new DateInterval('P6M');
                break;
            case 'ANNUALLY':
                $interval = new DateInterval('P1Y');
                break;
            case 'BIENNIALLY':
                $interval = new DateInterval('P2Y');
                break;
            default:
                error_log("Invalid repeat_cycle for invoice ID {$invoice['invoice_id']}: {$repeatCycle}");
                continue 2; // Skip if repeat_cycle is invalid
        }


        $nextCreatedAt = clone $createdAt;
        $nextCreatedAt->add($interval);

        // Check if the current date is within create_before days of the next creation date
        $createBeforeDate = clone $nextCreatedAt;
        $createBeforeDate->sub(new DateInterval('P' . $createBefore . 'D'));

        if ($currentDate < $createBeforeDate || $currentDate > $nextCreatedAt) {
            continue; // Skip if not within the create_before window
        }

        // Generate new invoice_number and transaction_id
        $newInvoiceNumber = generateInvoiceNumber($db);
        $newTransactionId = 'TX-' . $currentDate->format('Ymd') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5(uniqid()), 0, 4));

        $newDueDate = clone $nextCreatedAt;
        $newDueDate->add(new DateInterval('P7D')); // Example: due_date is 7 days after creation




        // Prepare new invoice data
        $newInvoice = [
            'invoice_number' => $newInvoiceNumber,
            'payment_method' => $invoice['payment_method'],
            'transaction_id' => $newTransactionId,
            'status' => 'PENDING',
            'amount' => $invoice['amount'],
            'quantity' => $invoice['quantity'],
            'tax' => $invoice['tax'],
            'discount' => $invoice['discount'],
            'total_amount' => $invoice['total_amount'], // Recalculated below
            'due_date' => $newDueDate->format('Y-m-d'),
            'customer_id' => $invoice['customer_id'],
            'service_id' => json_encode($invoice['service_id']),
            'description' => $invoice['description'],
            'created_by' => $invoice['created_by'],
            'from_date' => !empty($invoice['from_date']) ? $invoice['from_date'] : null,
            'to_date' => !empty($invoice['to_date']) ? $invoice['to_date'] : null,
            'invoice_type' => $invoice['invoice_type'],
            'invoice_title' => $invoice['invoice_title'],
            'gst_status' => 'HOLD',
            'repeat_cycle' => $invoice['repeat_cycle'],
            'create_before' => $invoice['create_before'],
            'reminder_enabled' => $invoice['reminder_enabled'],
            'reminder_count' => 0,
            'is_active' => 1
        ];


        $newInvoice['total_amount'] = ($newInvoice['amount'] * $newInvoice['quantity']) +
            ($newInvoice['amount'] * $newInvoice['quantity'] * strToNumber($invoice['tax_rate']) / 100) -
            $newInvoice['discount'];



        // echo "<pre>";
        // print_r($newInvoice);
        // print_r($newInvoice['service_id']);
        // exit;

        // Insert new invoice
        $sql = "INSERT INTO `invoice` (
    `invoice_number`, `payment_method`, `transaction_id`, `status`, `amount`, 
    `quantity`, `tax`, `discount`, `total_amount`, `due_date`, 
    `customer_id`, `service_id`, `description`, `created_by`, 
    `from_date`, `to_date`, `invoice_type`, `invoice_title`, 
    `gst_status`, `repeat_cycle`, `create_before`, `reminder_enabled`, 
    `reminder_count`, `is_active`
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Prepare failed: ' . $db->error);
        }

        $stmt->bind_param(
            'ssssdiiddissssisssssiiii', // Updated type string
            $newInvoice['invoice_number'],    // s (string)
            $newInvoice['payment_method'],    // s (string)
            $newInvoice['transaction_id'],    // s (string)
            $newInvoice['status'],            // s (string)
            $newInvoice['amount'],            // d (double)
            $newInvoice['quantity'],          // i (integer)
            $newInvoice['tax'],               // i (integer)
            $newInvoice['discount'],          // d (double)
            $newInvoice['total_amount'],      // d (double)
            $newInvoice['due_date'],          // s (string)
            $newInvoice['customer_id'],       // i (integer)
            $newInvoice['service_id'],        // s (string - JSON)
            $newInvoice['description'],       // s (string)
            $newInvoice['created_by'],        // i (integer)
            $newInvoice['from_date'],         // s (string)
            $newInvoice['to_date'],           // s (string)
            $newInvoice['invoice_type'],      // s (string - ENUM)
            $newInvoice['invoice_title'],     // s (string)
            $newInvoice['gst_status'],        // s (string)
            $newInvoice['repeat_cycle'],      // s (string)
            $newInvoice['create_before'],     // i (integer)
            $newInvoice['reminder_enabled'],  // i (integer)
            $newInvoice['reminder_count'],    // i (integer)
            $newInvoice['is_active']          // i (integer)
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to create new invoice: ' . $stmt->error);
        }

        $stmt->close();
        // Log success
        echo "New invoice created: {$newInvoice['invoice_number']}";

    }

} catch (\Throwable $th) {
    echo $th->getMessage();
}

?>