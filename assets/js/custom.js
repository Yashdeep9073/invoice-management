if (window.history.replaceState) {
  window.history.replaceState(null, null, window.location.href);
}

function getActiveTableId() {
  const activeTabPane = $(".tab-pane.fade.show.active");
  if (!activeTabPane.length) {
    // Check if jQuery object has elements
    console.log("No tab is active");
    return null;
  }

  const activeTabId = activeTabPane[0].id; // Get the ID of the active tab pane element

  // Map tab IDs to their corresponding table IDs
  const tabToTableMap = {
    "all-report": "allTable",
    "paid-report": "paidTable",
    "pending-report": "pendingTable",
    "cancelled-report": "cancelledTable",
    "refunded-report": "refundedTable",
    "transaction-report": "transactionTable",
  };

  const result = tabToTableMap[activeTabId] || null;
  return result;
}

function exportActiveTabToExcel() {
  // Initialize Notyf for success and error notifications
  const notyf = new Notyf({
    position: {
      x: "center",
      y: "top",
    },
    types: [
      {
        type: "success",
        background: "#4dc76f",
        textColor: "#FFFFFF",
        dismissible: false,
        duration: 3000,
      },
      {
        type: "error",
        background: "#ff1916",
        textColor: "#FFFFFF",
        dismissible: false,
        duration: 3000,
      },
    ],
  });

  const activeTableId = getActiveTableId();

  if (!activeTableId) {
    notyf.error("No active table found for export!");
    return;
  }

  let table = $(`#${activeTableId}`).DataTable();
  let selectedRows = [];

  // Get selected checkboxes - Corrected selector for your HTML
  // Find checked checkboxes within the table rows, excluding the 'select-all' checkbox
  let allCheckboxes = table.rows().nodes().to$().find('input[type="checkbox"]');
  let rowCheckboxes = allCheckboxes.filter(
    (i, cb) => !cb.id || !cb.id.startsWith("select-all")
  ); // Exclude select-all
  let checkedRowCheckboxes = rowCheckboxes.filter(":checked"); // Get only the checked ones

  // Determine rows to export based on checkbox selection
  if (checkedRowCheckboxes.length === 0) {
    // No specific rows selected, get all rows from the DataTable
    // Use DataTable's data() method or rows().data() to get actual data rows
    // This avoids potential issues with DOM nodes if the table is empty via DataTables
    let allNodes = table.rows().nodes().toArray();
    selectedRows = allNodes;
  } else {
    // Include the header row first
    let headerNodes = table.columns().header().toArray(); // Get header nodes array
    // Create a temporary row element containing header cells to add as the first selected row
    let headerRow = document.createElement("tr");
    headerRow.classList.add("header-row-temp"); // Optional: Add a class for identification later
    headerNodes.forEach((th) => {
      let cell = document.createElement("th");
      cell.textContent = th.textContent; // Copy text content
      headerRow.appendChild(cell);
    });
    selectedRows = [headerRow]; // Start with the header row

    // Iterate through DataTable rows to match checked checkboxes
    table.rows().every(function (rowIdx) {
      let rowData = this.data(); // Get data for current row (optional, for debugging)
      let rowNode = this.node(); // Get the actual DOM node for the row
      let rowCheckbox = $(rowNode).find('input[type="checkbox"]'); // Find the checkbox in this specific row

      if (rowCheckbox.length && rowCheckbox.is(":checked")) {
        selectedRows.push(rowNode);
      }
    });
  }

  // Check if there are any DATA rows to export (excluding the header row if it was added)
  // A table with only a header row (or just the header we added for selected export) should trigger the error
  let dataRowsCount = selectedRows.length;
  // If the first element added was a temporary header for selected rows, subtract 1
  if (
    selectedRows.length > 0 &&
    selectedRows[0].classList &&
    selectedRows[0].classList.contains("header-row-temp")
  ) {
    dataRowsCount -= 1; // Exclude the temporary header row from the count
  }

  if (dataRowsCount === 0) {
    notyf.error("No data rows available for export in the active tab!");
    return;
  }

  // Create a new table for export
  let tempTable = document.createElement("table");
  for (let row of selectedRows) {
    tempTable.appendChild(row.cloneNode(true));
  }

  // Remove the Action column if it exists
  let actionColumnIndex = -1;
  let headerCells = tempTable.rows[0]?.cells; // Use optional chaining to avoid errors if no rows
  if (headerCells) {
    for (let i = 0; i < headerCells.length; i++) {
      if (headerCells[i].innerText?.trim().toLowerCase() === "action") {
        // Use optional chaining
        actionColumnIndex = i;
        break;
      }
    }
    if (actionColumnIndex !== -1) {
      for (let row of tempTable.rows) {
        if (row.cells.length > actionColumnIndex) {
          row.deleteCell(actionColumnIndex);
        }
      }
    }
  }

  // Remove the checkbox column (first column) and currency symbols
  for (let row of tempTable.rows) {
    if (row.cells.length > 0) {
      row.deleteCell(0); // Remove checkbox column
    }
    for (let cell of row.cells) {
      if (cell.innerText) {
        // Check if innerText exists before replacing
        cell.innerText = cell.innerText.replace(/[₹$€£¥]/g, "").trim(); // Remove currency symbols
      }
    }
  }

  // Export to Excel
  try {
    let workbook = XLSX.utils.table_to_book(tempTable, { sheet: "Sheet1" });
    XLSX.writeFile(
      workbook,
      `${activeTableId.replace("Table", "")}_invoices.xlsx`
    );
    notyf.success("Excel file exported successfully!");
  } catch (error) {
    console.error("Excel export error:", error); // Log the error for debugging
    notyf.error("Error exporting to Excel: " + error.message);
  }
}

// Similarly corrected PDF function
function exportActiveTabToPDF() {
  const notyf = new Notyf({
    position: {
      x: "center",
      y: "top",
    },
    types: [
      {
        type: "success",
        background: "#4dc76f",
        textColor: "#FFFFFF",
        dismissible: false,
        duration: 3000,
      },
      {
        type: "error",
        background: "#ff1916",
        textColor: "#FFFFFF",
        dismissible: false,
        duration: 3000,
      },
    ],
  });

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  const activeTableId = getActiveTableId();

  if (!activeTableId) {
    notyf.error("No active table found for export!");
    return;
  }

  let table = $(`#${activeTableId}`).DataTable();
  let selectedRows = [];

  let allCheckboxes = table.rows().nodes().to$().find('input[type="checkbox"]');
  let rowCheckboxes = allCheckboxes.filter(
    (i, cb) => !cb.id || !cb.id.startsWith("select-all")
  );
  let checkedRowCheckboxes = rowCheckboxes.filter(":checked");

  if (checkedRowCheckboxes.length === 0) {
    selectedRows = table.rows().nodes().toArray();
  } else {
    let headerNodes = table.columns().header().toArray();
    let headerRow = document.createElement("tr");
    headerRow.classList.add("header-row-temp");
    headerNodes.forEach((th) => {
      let cell = document.createElement("th");
      cell.textContent = th.textContent;
      headerRow.appendChild(cell);
    });
    selectedRows = [headerRow];

    table.rows().every(function (rowIdx) {
      let rowNode = this.node();
      let rowCheckbox = $(rowNode).find('input[type="checkbox"]');
      if (rowCheckbox.length && rowCheckbox.is(":checked")) {
        selectedRows.push(rowNode);
      }
    });
  }

  let dataRowsCount = selectedRows.length;
  if (
    selectedRows.length > 0 &&
    selectedRows[0].classList &&
    selectedRows[0].classList.contains("header-row-temp")
  ) {
    dataRowsCount -= 1;
  }

  if (dataRowsCount === 0) {
    notyf.error("No data rows available for export in the active tab!");
    return;
  }

  // Prepare data for PDF
  let tableData = [];
  let headers = [];

  if (selectedRows[0]?.classList?.contains("header-row-temp")) {
    // If it's our temporary header, get headers from its cells
    for (let i = 0; i < selectedRows[0].cells.length; i++) {
      let headerText =
        selectedRows[0].cells[i].innerText?.trim().toLowerCase() || "";
      if (headerText !== "action" && headerText !== "") {
        headers.push(selectedRows[0].cells[i].innerText);
      }
    }
  } else {
    // Otherwise, get headers from the original table's first row (the actual header)
    let originalHeaderCells = selectedRows[0].cells;
    for (let i = 0; i < originalHeaderCells.length; i++) {
      let headerText =
        originalHeaderCells[i].innerText?.trim().toLowerCase() || "";
      if (headerText !== "action" && headerText !== "") {
        headers.push(originalHeaderCells[i].innerText);
      }
    }
  }

  for (
    let i = selectedRows[0]?.classList?.contains("header-row-temp") ? 1 : 0;
    i < selectedRows.length;
    i++
  ) {
    // Start from 1 if temp header was added
    let rowData = [];
    for (let j = 0; j < selectedRows[i].cells.length; j++) {
      let cellText = selectedRows[i].cells[j].innerText?.trim() || "";
      if (
        j !== 0 && // Skip checkbox column
        selectedRows[i].cells[j].innerText?.trim().toLowerCase() !== "action" // Skip action column
      ) {
        cellText = cellText.replace(/[₹$€£¥]/g, "").trim();
        rowData.push(cellText);
      }
    }
    if (rowData.length > 0) {
      tableData.push(rowData);
    }
  }

  // Generate PDF
  try {
    doc.autoTable({
      head: [headers],
      body: tableData,
      theme: "striped",
      styles: { fontSize: 8 }, // Adjust font size as needed
      margin: { top: 20 },
    });
    doc.save(`${activeTableId.replace("Table", "")}_invoices.pdf`);
    notyf.success("PDF file exported successfully!");
  } catch (error) {
    console.error("PDF export error:", error); // Log the error for debugging
    notyf.error("Error exporting to PDF: " + error.message);
  }
}

// Function to export to Excel
function exportToExcel(name = "test") {
  // Initialize Notyf for success and error notifications
  const notyf = new Notyf({
    position: {
      x: "center",
      y: "top",
    },
    types: [
      {
        type: "success",
        background: "#4dc76f",
        textColor: "#FFFFFF",
        dismissible: false,
        duration: 3000,
      },
      {
        type: "error",
        background: "#ff1916",
        textColor: "#FFFFFF",
        dismissible: false,
        duration: 3000,
      },
    ],
  });

  let table = $("#myTable").DataTable(); // Initialize DataTables API
  let selectedRows = [];

  // Get selected invoice IDs
  let checkboxes = table
    .rows()
    .nodes()
    .to$()
    .find('input[name="invoiceIds"]:checked');
  let selectedInvoiceIds = checkboxes
    .map((i, checkbox) => checkbox.value)
    .get();

  // If no checkboxes are selected, include all rows; otherwise, filter rows
  if (selectedInvoiceIds.length === 0) {
    selectedRows = table.rows().nodes().toArray(); // Get all rows from DataTables
  } else {
    selectedRows = [table.rows().nodes().toArray()[0]]; // Include header row
    table
      .rows()
      .nodes()
      .each(function (row, index) {
        if (index === 0) return; // Skip header row to avoid duplication
        let checkbox = $(row).find('input[name="invoiceIds"]');
        if (checkbox.length && selectedInvoiceIds.includes(checkbox.val())) {
          selectedRows.push(row);
        }
      });
  }

  // Check if there are any rows to export (excluding header)
  if (selectedRows.length <= 1) {
    notyf.error("No rows selected for export!");
    return;
  }

  // Create a new table for export
  let tempTable = document.createElement("table");
  for (let row of selectedRows) {
    tempTable.appendChild(row.cloneNode(true));
  }

  // Remove the Action column
  let actionColumnIndex = -1;
  let headerCells = tempTable.rows[0].cells;
  for (let i = 0; i < headerCells.length; i++) {
    if (headerCells[i].innerText.trim().toLowerCase() === "action") {
      actionColumnIndex = i;
      break;
    }
  }
  if (actionColumnIndex !== -1) {
    for (let row of tempTable.rows) {
      if (row.cells.length > actionColumnIndex) {
        row.deleteCell(actionColumnIndex);
      }
    }
  }

  // Remove the checkbox column (first column) and currency symbols
  for (let row of tempTable.rows) {
    if (row.cells.length > 0) {
      row.deleteCell(0); // Remove checkbox column
    }
    for (let cell of row.cells) {
      cell.innerText = cell.innerText.replace(/[₹$]/g, "").trim(); // Remove currency symbols
    }
  }

  // Export to Excel
  try {
    let workbook = XLSX.utils.table_to_book(tempTable, { sheet: "Sheet1" });
    XLSX.writeFile(workbook, `${name}.xlsx`);
    notyf.success("Excel file exported successfully!");
  } catch (error) {
    notyf.error("Error exporting to Excel: " + error.message);
  }
}

// Function to export to PDF
function exportToPDF(name = "test") {
  // Initialize Notyf for success and error notifications
  const notyf = new Notyf({
    position: {
      x: "center",
      y: "top",
    },
    types: [
      {
        type: "success",
        background: "#4dc76f",
        textColor: "#FFFFFF",
        dismissible: false,
        duration: 3000,
      },
      {
        type: "error",
        background: "#ff1916",
        textColor: "#FFFFFF",
        dismissible: false,
        duration: 3000,
      },
    ],
  });

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();
  let table = $("#myTable").DataTable(); // Initialize DataTables API
  let selectedRows = [];

  // Get selected invoice IDs
  let checkboxes = table
    .rows()
    .nodes()
    .to$()
    .find('input[name="invoiceIds"]:checked');
  let selectedInvoiceIds = checkboxes
    .map((i, checkbox) => checkbox.value)
    .get();

  // If no checkboxes are selected, include all rows; otherwise, filter rows
  if (selectedInvoiceIds.length === 0) {
    selectedRows = table.rows().nodes().toArray(); // Get all rows from DataTables
  } else {
    selectedRows = [table.rows().nodes().toArray()[0]]; // Include header row
    table
      .rows()
      .nodes()
      .each(function (row, index) {
        if (index === 0) return; // Skip header row to avoid duplication
        let checkbox = $(row).find('input[name="invoiceIds"]');
        if (checkbox.length && selectedInvoiceIds.includes(checkbox.val())) {
          selectedRows.push(row);
        }
      });
  }

  // Check if there are any rows to export (excluding header)
  if (selectedRows.length <= 1) {
    notyf.error("No rows selected for export!");
    return;
  }

  // Prepare data for PDF
  let tableData = [];
  let headers = [];
  for (let i = 0; i < selectedRows[0].cells.length; i++) {
    let headerText = selectedRows[0].cells[i].innerText.trim().toLowerCase();
    if (headerText !== "action" && headerText !== "") {
      // Skip Action and checkbox columns
      headers.push(selectedRows[0].cells[i].innerText);
    }
  }

  for (let i = 1; i < selectedRows.length; i++) {
    let rowData = [];
    for (let j = 0; j < selectedRows[i].cells.length; j++) {
      let cellText = selectedRows[i].cells[j].innerText.trim();
      if (
        j !== 0 &&
        selectedRows[i].cells[j].innerText.trim().toLowerCase() !== "action"
      ) {
        // Skip checkbox and Action columns
        // Remove currency symbols (₹, $) from cell text
        cellText = cellText.replace(/[₹$]/g, "").trim();
        rowData.push(cellText);
      }
    }
    if (rowData.length > 0) {
      tableData.push(rowData);
    }
  }

  // Generate PDF
  try {
    doc.autoTable({
      head: [headers],
      body: tableData,
      theme: "striped",
      styles: { fontSize: 10 },
      margin: { top: 20 },
    });
    doc.save(`${name}.pdf`);
    notyf.success("PDF file exported successfully!");
  } catch (error) {
    notyf.error("Error exporting to PDF: " + error.message);
  }
}
