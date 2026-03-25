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


function getTableHeaderRow(tableId) {
  const tableEl = document.getElementById(tableId);
  if (!tableEl) return null;

  const thead = tableEl.querySelector("thead");
  if (!thead) return null;

  const headerRow = document.createElement("tr");
  thead.querySelectorAll("th").forEach(th => {
    const newTh = document.createElement("th");
    newTh.innerText = th.innerText.trim();
    headerRow.appendChild(newTh);
  });

  return headerRow;
}

function getCustomerNameFromPage() {
  const el = document.querySelector(".profile-info h5");
  return el ? el.innerText.trim() : "Customer Report";
}


function getCustomerInfoFromPage() {
  const normalizeLabel = (text = "") =>
    text
      .replace(/\s+/g, " ")
      .replace(/:$/, "")
      .trim()
      .toLowerCase();

  const getValueByLabels = (labels = []) => {
    const wanted = labels.map((label) => normalizeLabel(label));
    const rows = document.querySelectorAll(".customer-card tr");

    for (const row of rows) {
      const headerCell = row.querySelector(".customer-header");
      if (!headerCell) continue;

      const label = normalizeLabel(headerCell.innerText || "");
      if (!wanted.includes(label)) continue;

      const dataCells = row.querySelectorAll("td");
      if (dataCells.length < 2) continue;

      const value = (dataCells[1].innerText || "").trim();
      if (value) return value;
    }

    return "-";
  };

  const nameEl = document.querySelector(".profile-info h5");

  return {
    name: nameEl ? nameEl.innerText.trim() : "Customer Report",
    email: getValueByLabels(["Email", "Customer Email"]),
    gstNo: getValueByLabels(["GST No"]),
    address: getValueByLabels(["Address", "Customer Address"]),
  };
}


function exportActiveTabLedgerToExcel() {
  const notyf = new Notyf({ position: { x: "center", y: "top" } });

  const activeTableId = getActiveTableId();
  if (!activeTableId) {
    notyf.error("No active table found!");
    return;
  }

  const tableEl = document.getElementById(activeTableId);
  const table = $(`#${activeTableId}`).DataTable();

  if (!tableEl || !tableEl.tHead) {
    notyf.error("Table structure invalid!");
    return;
  }

  // ===============================
  // 🔹 Helpers
  // ===============================

  const cleanText = (text) => {
    return text
      ?.replace(/[₹$€£¥,]/g, "") // remove currency + commas
      .replace(/\s+/g, " ")
      .trim() || "";
  };

  const getSelectedRows = () => {
    const allRows = table.rows().nodes().toArray();

    const checkboxes = table
      .rows()
      .nodes()
      .to$()
      .find('input[type="checkbox"]')
      .filter((i, cb) => !cb.id || !cb.id.startsWith("select-all"));

    const checked = checkboxes.filter(":checked");

    if (checked.length === 0) return allRows;

    return allRows.filter((row) => {
      const cb = $(row).find('input[type="checkbox"]');
      return cb.length && cb.is(":checked");
    });
  };

  const getActionColumnIndex = (headerRow) => {
    for (let i = 0; i < headerRow.cells.length; i++) {
      if (headerRow.cells[i].innerText.trim().toLowerCase() === "action") {
        return i;
      }
    }
    return -1;
  };

  const cleanTable = (tableNode, actionIndex) => {
    for (let row of tableNode.rows) {

      if (!row.cells || row.cells.length === 0) continue;

      // Skip title row (colspan row)
      if (row.cells.length === 1 && row.cells[0].colSpan > 1) continue;

      // Remove checkbox column
      if (row.cells.length > 0) row.deleteCell(0);

      // Remove action column
      if (actionIndex !== -1 && row.cells.length > actionIndex) {
        row.deleteCell(actionIndex);
      }

      // Clean values
      for (let cell of row.cells) {
        if (cell.innerText) {
          cell.innerText = cleanText(cell.innerText);
        }
      }
    }
  };

  // ===============================
  // 🔹 Data Extraction
  // ===============================

  const selectedRows = getSelectedRows();

  if (!selectedRows.length) {
    notyf.error("No data to export!");
    return;
  }

  const headerRow = tableEl.tHead.rows[0];
  const actionIndex = getActionColumnIndex(headerRow);

  const customerName = getCustomerNameFromPage();
  const customerInfo = getCustomerInfoFromPage();

  // ===============================
  // 🔹 Build Export Table
  // ===============================

  const tempTable = document.createElement("table");

  // ---- TITLE ROW ----
  const titleRow = document.createElement("tr");
  const titleCell = document.createElement("th");
  titleCell.colSpan = headerRow.cells.length;
  titleCell.innerText = `Customer: ${customerName} | Email: ${customerInfo.email} | GST: ${customerInfo.gstNo} | Address: ${customerInfo.address}`;
  titleCell.style.fontWeight = "bold";
  titleCell.style.textAlign = "center";
  titleRow.appendChild(titleCell);
  tempTable.appendChild(titleRow);

  // ---- EMPTY ROW ----
  tempTable.appendChild(document.createElement("tr"));

  // ---- THEAD ----
  tempTable.appendChild(tableEl.tHead.cloneNode(true));

  // ---- TBODY ----
  const tbody = document.createElement("tbody");
  selectedRows.forEach((row) => {
    tbody.appendChild(row.cloneNode(true));
  });
  tempTable.appendChild(tbody);

  // ---- TFOOT (NEW FIX) ----
  if (tableEl.tFoot) {
    tempTable.appendChild(tableEl.tFoot.cloneNode(true));
  }

  // ===============================
  // 🔹 Cleanup
  // ===============================
  cleanTable(tempTable, actionIndex);

  // ===============================
  // 🔹 Export
  // ===============================
  try {
    const wb = XLSX.utils.table_to_book(tempTable, {
      sheet: "Ledger",
    });

    XLSX.writeFile(wb, `${activeTableId}.xlsx`);
    notyf.success("Excel exported successfully!");
  } catch (err) {
    console.error(err);
    notyf.error("Excel export failed!");
  }
}

function exportActiveTabLedgerToPDF() {
  const notyf = new Notyf({ position: { x: "center", y: "top" } });
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF("l");

  const activeTableId = getActiveTableId();
  if (!activeTableId) {
    notyf.error("No active table found!");
    return;
  }

  const table = $(`#${activeTableId}`).DataTable();
  const headerRow = getTableHeaderRow(activeTableId);

  if (!headerRow) {
    notyf.error("Table header not found!");
    return;
  }

  // ---------- HEADERS ----------
  let headers = [];
  [...headerRow.cells].forEach(th => {
    const txt = th.innerText.trim().toLowerCase();
    if (txt && txt !== "action") headers.push(th.innerText);
  });

  // ---------- ROWS ----------
  let rows = [];
  table.rows().every(function () {
    let rowNode = this.node();
    let cb = $(rowNode).find('input[type="checkbox"]');

    if (cb.length && cb.is(":checked") || !$('input[type="checkbox"]:checked').length) {
      let rowData = [];
      [...rowNode.cells].forEach((cell, idx) => {
        if (idx === 0) return; // checkbox
        if (cell.innerText.toLowerCase() === "action") return;

        rowData.push(
          cell.innerText.replace(/[₹$€£¥]/g, "").trim()
        );
      });
      if (rowData.length) rows.push(rowData);
    }
  });

  if (!rows.length) {
    notyf.error("No data rows to export!");
    return;
  }

  // ---------- PDF ----------
  try {
    doc.autoTable({
      head: [headers],
      body: rows,
      theme: "striped",
      styles: { fontSize: 8 },
      margin: { top: 20 }
    });
    doc.save(`${activeTableId}.pdf`);
    notyf.success("PDF exported successfully!");
  } catch (err) {
    console.error(err);
    notyf.error("PDF export failed!");
  }
}


// Function to export to Excel
function exportToExcel(name = "export") {
  const notyf = new Notyf({
    position: { x: "center", y: "top" },
    types: [
      { type: "success", background: "#4dc76f", textColor: "#fff", duration: 3000 },
      { type: "error", background: "#ff1916", textColor: "#fff", duration: 3000 },
    ],
  });

  const table = $("#myTable").DataTable();
  const originalTable = document.getElementById("myTable");

  // ===============================
  // 🔹 Helpers
  // ===============================

  const cleanText = (text) => {
    return text
      ?.replace(/[₹$,]/g, "") // remove currency + commas
      .replace(/\s+/g, " ")
      .trim() || "";
  };

  const removeColumns = (tableEl) => {
    let actionIndex = -1;

    // detect action column
    const headers = tableEl.querySelectorAll("thead th");
    headers.forEach((th, i) => {
      if (th.innerText.trim().toLowerCase() === "action") {
        actionIndex = i;
      }
    });

    for (let row of tableEl.rows) {
      // remove checkbox column (first)
      if (row.cells.length > 0) row.deleteCell(0);

      // remove action column
      if (actionIndex !== -1 && row.cells.length > actionIndex) {
        row.deleteCell(actionIndex);
      }

      // clean text
      for (let cell of row.cells) {
        cell.innerText = cleanText(cell.innerText);
      }
    }
  };

  const getSelectedRows = () => {
    const checkboxes = table
      .rows()
      .nodes()
      .to$()
      .find('input[name="invoiceIds"]:checked');

    const selectedIds = checkboxes.map((i, el) => el.value).get();

    if (selectedIds.length === 0) {
      return table.rows().nodes().toArray();
    }

    return table
      .rows()
      .nodes()
      .toArray()
      .filter((row) => {
        const checkbox = $(row).find('input[name="invoiceIds"]');
        return checkbox.length && selectedIds.includes(checkbox.val());
      });
  };

  // ===============================
  // 🔹 Build Export Table
  // ===============================

  const selectedRows = getSelectedRows();

  if (!selectedRows.length) {
    notyf.error("No rows selected for export!");
    return;
  }

  const tempTable = document.createElement("table");

  // ✅ THEAD
  if (originalTable.tHead) {
    tempTable.appendChild(originalTable.tHead.cloneNode(true));
  }

  // ✅ TBODY
  const tbody = document.createElement("tbody");
  selectedRows.forEach((row) => {
    tbody.appendChild(row.cloneNode(true));
  });
  tempTable.appendChild(tbody);

  // ✅ TFOOT (important fix)
  if (originalTable.tFoot) {
    tempTable.appendChild(originalTable.tFoot.cloneNode(true));
  }

  // ===============================
  // 🔹 Clean & Normalize
  // ===============================
  removeColumns(tempTable);

  // ===============================
  // 🔹 Export
  // ===============================
  try {
    const workbook = XLSX.utils.table_to_book(tempTable, {
      sheet: "Ledger",
    });

    XLSX.writeFile(workbook, `${name}.xlsx`);
    notyf.success("Excel exported successfully!");
  } catch (err) {
    console.error(err);
    notyf.error("Export failed: " + err.message);
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
