if (window.history.replaceState) {
  window.history.replaceState(null, null, window.location.href);
}

function exportToExcel() {
  let table = document.getElementById("myTable");
  let workbook = XLSX.utils.table_to_book(table, { sheet: "Sheet1" });
  XLSX.writeFile(workbook, "sample.xlsx");
}

function exportToPDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  doc.autoTable({ html: "#myTable" });
  doc.save("sample.pdf");
}
