<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Reminder</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header {
            background-color: #007bff;
            padding: 20px;
            text-align: center;
            color: #ffffff;
        }

        .header img {
            max-width: 150px;
            height: auto;
            background-color: #fff;
        }

        .header h1 {
            margin: 10px 0;
            font-size: 24px;
        }

        .content {
            padding: 20px;
        }

        .content p {
            line-height: 1.6;
            color: #333333;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .invoice-table th,
        .invoice-table td {
            border: 1px solid #dddddd;
            padding: 12px;
            text-align: left;
        }

        .invoice-table th {
            background-color: #007bff;
            color: #ffffff;
            font-weight: bold;
        }

        .invoice-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .invoice-table tr:hover {
            background-color: #f1f1f1;
        }

        .footer {
            background-color: #f4f4f4;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #666666;
        }

        .footer a {
            color: #007bff;
            text-decoration: none;
            margin: 0 10px;
        }

        .footer img {
            width: 24px;
            height: 24px;
            vertical-align: middle;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }

        @media only screen and (max-width: 600px) {
            .container {
                width: 100%;
                margin: 10px;
            }

            .header img {
                max-width: 120px;
            }

            .header h1 {
                font-size: 20px;
            }

            .invoice-table th,
            .invoice-table td {
                font-size: 14px;
                padding: 8px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header with Logo -->
        <div class="header">
            <img src="https://vibrantick.in/assets/images/logo/footer.png" alt="Vibrantick InfoTech Solution Logo">
            <h1>Payment Reminder</h1>
        </div>
        <!-- Content -->
        <div class="content">
            <p>Dear Alice Smith,</p>
            <p>We hope this message finds you well. The following invoice(s) are overdue. Kindly make the payment at
                your earliest convenience to avoid any service interruptions.</p>
            <!-- Invoice Table -->
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Invoice Number</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Tax</th>
                        <th>Discount</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>VIS-20250422-24138</td>
                        <td>2025-05-02</td>
                        <td>₹500.00</td>
                        <td>12%</td>
                        <td>1.00</td>
                        <td>₹585.00</td>
                    </tr>
                    <tr>
                        <td>VIS-20250423-34546</td>
                        <td>2025-05-10</td>
                        <td>₹2334.00</td>
                        <td>14%</td>
                        <td>0.00</td>
                        <td>₹2363.18</td>
                    </tr>
                </tbody>
            </table>
            <!-- Call to Action -->
            <p>Please settle the outstanding amount at your earliest convenience. For any questions or assistance,
                contact our support team at <a href="mailto:support@vibrantick.org">support@vibrantick.org</a> or call
                <a href="tel:+919870443528">+91-9870443528</a>.</p>
            <p>Thank you for your prompt attention to this matter.</p>
            <p>Best regards,<br>Vibrantick InfoTech Solution Team</p>
        </div>
        <!-- Footer -->
        <div class="footer">
            <p>© 2025 Vibrantick InfoTech Solution. All rights reserved.</p>
            <p>Vibrantick InfoTech Solution | D-185, Phase 8B, Sector 74, SAS Nagar | <a
                    href="mailto:support@vibrantick.org">support@vibrantick.org</a></p>
            <p>
                <a href="https://www.linkedin.com/company/vibrantick-infotech-solutions/posts/?feedView=all"
                    target="_blank">
                    <img src="https://cdn-icons-png.flaticon.com/24/174/174857.png" alt="LinkedIn">
                </a>
                <a href="https://www.instagram.com/vibrantickinfotech/" target="_blank">
                    <img src="https://cdn-icons-png.flaticon.com/24/2111/2111463.png" alt="Instagram">
                </a>
                <a href="https://www.facebook.com/vibranticksolutions/" target="_blank">
                    <img src="https://cdn-icons-png.flaticon.com/24/733/733547.png" alt="Facebook">
                </a>
            </p>
        </div>
    </div>
</body>

</html>