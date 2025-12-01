<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Profiles - VocAItion</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
    body { background: #f4f6f8; padding: 20px; }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 10px;
    }
    .header h1 { color: #1d3557; }

    .btn {
      padding: 8px 14px;
      border: none;
      border-radius: 6px;
      background: #1d3557;
      color: #fff;
      cursor: pointer;
      transition: 0.3s;
    }
    .btn:hover { background: #457b9d; }

    /* Search Bar */
    .search-bar {
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .search-bar input {
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 6px;
      flex: 1;
    }

    /* Table */
    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    table thead {
      background: #1d3557;
      color: #fff;
    }
    table th, table td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }
    table tbody tr:hover {
      background: #f1f1f1;
    }

    .action-btns button {
      padding: 6px 10px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      margin-right: 5px;
      color: #fff;
    }
    .edit { background: #457b9d; }
    .report { background: #e63946; }
    .remove { background: #6c757d; }
    .reset { background: #2a9d8f; }

    /* User Activity Log */
    .log {
      background: #fff;
      padding: 15px;
      margin-top: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .log h2 {
      margin-bottom: 10px;
      color: #1d3557;
    }
    .log ul {
      list-style: none;
      max-height: 150px;
      overflow-y: auto;
      padding-left: 0;
    }
    .log li {
      font-size: 14px;
      padding: 5px 0;
      border-bottom: 1px solid #ddd;
    }

    /* Modal */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.5);
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      width: 300px;
      text-align: center;
    }
    .modal-content h2 {
      margin-bottom: 15px;
      color: #1d3557;
    }
    .modal-content input, .modal-content select {
      width: 100%;
      padding: 8px;
      margin-bottom: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    .modal-actions {
      display: flex;
      justify-content: space-between;
      margin-top: 10px;
    }
    .close-btn {
      background: #e63946;
    }
  </style>
</head>
<body>

  <!-- Header -->
  <div class="header">
    <h1>Student Profiles</h1>
    <div>
      <button class="btn" onclick="openAddUserModal()">+ Add User</button>
      <button class="btn" onclick="generatePDFReport()">Generate Student Report (PDF)</button>
    </div>
  </div>

  <!-- Search Bar -->
  <div class="search-bar">
    <input type="text" id="searchInput" placeholder="Search Student..." onkeyup="searchTable()">
    <button class="btn" onclick="searchTable()">Search</button>
  </div>

  <!-- Student Table -->
  <table id="studentTable">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Strand</th>
        <th>Survey Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>001</td>
        <td>Juan Dela Cruz</td>
        <td>STEM</td>
        <td>Completed</td>
        <td class="action-btns">
          <button class="edit" onclick="editStudent('001')"><i class="fas fa-edit"></i> Edit</button>
          <button class="report" onclick="viewReport('001')"><i class="fas fa-file-alt"></i> Report</button>
          <button class="reset" onclick="resetPassword('001')"><i class="fas fa-key"></i></button>
          <button class="remove" onclick="removeUser(this, '001')"><i class="fas fa-trash"></i></button>
        </td>
      </tr>
      <tr>
        <td>002</td>
        <td>Maria Santos</td>
        <td>ABM</td>
        <td>Pending</td>
        <td class="action-btns">
          <button class="edit" onclick="editStudent('002')"><i class="fas fa-edit"></i> Edit</button>
          <button class="report" onclick="viewReport('002')"><i class="fas fa-file-alt"></i> Report</button>
          <button class="reset" onclick="resetPassword('002')"><i class="fas fa-key"></i></button>
          <button class="remove" onclick="removeUser(this, '002')"><i class="fas fa-trash"></i></button>
        </td>
      </tr>
    </tbody>
  </table>

  <!-- User Activity Log -->
  <div class="log">
    <h2>User Activity Log</h2>
    <ul id="activityLog">
      <li>System initialized.</li>
    </ul>
  </div>

  <!-- Add User Modal -->
  <div id="addUserModal" class="modal">
    <div class="modal-content">
      <h2>Add New Student</h2>
      <input type="text" id="newStudentName" placeholder="Enter Student Name">
      <select id="newStudentStrand">
        <option value="">Select Strand</option>
        <option value="STEM">STEM</option>
        <option value="ABM">ABM</option>
        <option value="HUMSS">HUMSS</option>
        <option value="GAS">GAS</option>
        <option value="TVL">TVL</option>
      </select>
      <div class="modal-actions">
        <button class="btn" onclick="saveNewStudent()">Save</button>
        <button class="btn close-btn" onclick="closeAddUserModal()">Cancel</button>
      </div>
    </div>
  </div>

  <script>
    // Log Activity
    function logActivity(message) {
      const logList = document.getElementById("activityLog");
      const li = document.createElement("li");
      li.textContent = new Date().toLocaleTimeString() + " - " + message;
      logList.appendChild(li);
    }

    // Search Filter
    function searchTable() {
      let input = document.getElementById("searchInput").value.toLowerCase();
      let rows = document.querySelectorAll("#studentTable tbody tr");
      rows.forEach(row => {
        let name = row.cells[1].textContent.toLowerCase();
        row.style.display = name.includes(input) ? "" : "none";
      });
    }

    // Open Modal
    function openAddUserModal() {
      document.getElementById("addUserModal").style.display = "flex";
    }

    // Close Modal
    function closeAddUserModal() {
      document.getElementById("addUserModal").style.display = "none";
    }

    // Save New Student
    function saveNewStudent() {
      const name = document.getElementById("newStudentName").value.trim();
      const strand = document.getElementById("newStudentStrand").value;

      if (!name || !strand) {
        alert("Please fill out all fields.");
        return;
      }

      const table = document.getElementById("studentTable").querySelector("tbody");
      const newID = String(table.rows.length + 1).padStart(3, '0');

      const newRow = table.insertRow();
      newRow.innerHTML = `
        <td>${newID}</td>
        <td>${name}</td>
        <td>${strand}</td>
        <td>Pending</td>
        <td class="action-btns">
          <button class="edit" onclick="editStudent('${newID}')"><i class="fas fa-edit"></i> Edit</button>
          <button class="report" onclick="viewReport('${newID}')"><i class="fas fa-file-alt"></i> Report</button>
          <button class="reset" onclick="resetPassword('${newID}')"><i class="fas fa-key"></i></button>
          <button class="remove" onclick="removeUser(this, '${newID}')"><i class="fas fa-trash"></i></button>
        </td>`;
      
      logActivity(`Added new student: ${name} (${strand})`);
      closeAddUserModal();
      document.getElementById("newStudentName").value = "";
      document.getElementById("newStudentStrand").value = "";
    }

    // Remove User
    function removeUser(button, id) {
      if (confirm("Are you sure you want to remove Student ID: " + id + "?")) {
        button.closest("tr").remove();
        logActivity("Removed Student ID: " + id);
      }
    }

    // Reset Password
    function resetPassword(id) {
      alert("Password reset link sent for Student ID: " + id);
      logActivity("Password reset for Student ID: " + id);
    }

    // Edit Student
    function editStudent(id) {
      alert("Edit details for Student ID: " + id);
      logActivity("Editing Student ID: " + id);
    }

    // View Report
    function viewReport(id) {
      alert("Viewing report for Student ID: " + id);
      logActivity("Viewed report for Student ID: " + id);
    }

    // Generate PDF Report
    function generatePDFReport() {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF();

      doc.setFontSize(18);
      doc.text("Student Report", 14, 20);
      doc.setFontSize(12);
      doc.text("Generated on: " + new Date().toLocaleString(), 14, 30);

      // Collect table data
      const table = document.getElementById("studentTable");
      const rows = [];
      const headers = [];

      table.querySelectorAll("thead th").forEach(th => headers.push(th.innerText));

      table.querySelectorAll("tbody tr").forEach(tr => {
        const rowData = [];
        tr.querySelectorAll("td").forEach((td, index) => {
          if (index < 4) rowData.push(td.innerText); // Skip action buttons
        });
        rows.push(rowData);
      });

      // Add table to PDF
      doc.autoTable({
        head: [headers.slice(0, 4)], // Only ID, Name, Strand, Survey Status
        body: rows,
        startY: 40,
      });

      doc.save("student_report.pdf");
      logActivity("Generated student report (PDF).");
    }
  </script>
</body>
</html>
