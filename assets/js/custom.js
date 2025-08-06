if (window.history.replaceState) {
  window.history.replaceState(null, null, window.location.href);
}

function exportToExcel() {
  let table = document.getElementById("myTable");
  let tableClone = table.cloneNode(true);

  let actionColumnIndex = -1;
  // get index of column 
  let headerCells = tableClone.rows[0].cells;
  for (let i = 0; i < headerCells.length; i++) {
    if (headerCells[i].innerText.trim().toLowerCase() === "action") {
      actionColumnIndex = i;
      break;
    }
  }
  // del column
  if (actionColumnIndex !== -1) {
    for (let row of tableClone.rows) {
      if (row.cells.length > actionColumnIndex) {
        row.deleteCell(actionColumnIndex);
      }
    }
  }

  let workbook = XLSX.utils.table_to_book(tableClone, { sheet: "Sheet1" });
  XLSX.writeFile(workbook, "sample.xlsx");
}

// function exportToPDF() {
//   const { jsPDF } = window.jspdf;
//   const doc = new jsPDF();

//   doc.autoTable({ html: "#myTable" });
//   doc.save("sample.pdf");
// }

function exportToPDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  const table = document.getElementById("myTable");
  const headers = [];
  const data = [];

  // Get all headers
  const ths = table.querySelectorAll("thead th");
  ths.forEach((th) => headers.push(th.innerText.trim()));

  // Find index of "Action" column
  const actionIndex = headers.indexOf("Action");

  // Remove "Action" from headers
  if (actionIndex !== -1) headers.splice(actionIndex, 1);

  // Get rows from tbody
  const trs = table.querySelectorAll("tbody tr");
  trs.forEach((tr) => {
    const rowData = [];
    const tds = tr.querySelectorAll("td");
    tds.forEach((td, index) => {
      if (index !== actionIndex) {
        const clone = td.cloneNode(true);
        clone.querySelectorAll("svg, input, span, a, label").forEach(el => el.remove());

        let text = clone.textContent.trim();

        // Remove ₹ symbol specifically
        text = text.replace(/[₹$]/g, '').trim();


        rowData.push(text);
      }
    });
    data.push(rowData);
  });

  // Generate PDF
  doc.autoTable({
    head: [headers],
    body: data,
  });

  doc.save("invoice-table.pdf");
}

