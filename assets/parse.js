/* Reads an attendance export (xlsx / xls / csv) into rows:
   [employeeId, name, department, 'YYYY-MM-DD', 'HH:MM' first punch, 'HH:MM' last punch] */
(function () {
  var pad = function (n) { return String(n).padStart(2, '0'); };
  function normDate(v) {
    var s = String(v == null ? '' : v).trim();
    if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
    var m = s.match(/^(\d{4})[\/.](\d{1,2})[\/.](\d{1,2})/);
    if (m) return m[1] + '-' + pad(m[2]) + '-' + pad(m[3]);
    m = s.match(/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2,4})/);
    if (m) {
      var a = +m[1], b = +m[2], y = m[3].length === 2 ? 2000 + +m[3] : +m[3];
      if (b > 12) { var t = a; a = b; b = t; }
      return y + '-' + pad(b) + '-' + pad(a);
    }
    var d = new Date(s);
    return isNaN(d) ? s : d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }
  function normTime(v) {
    var s = String(v == null ? '' : v).trim();
    var m = s.match(/(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?/);
    if (!m) return '';
    var h = +m[1];
    if (m[3]) { var pm = /p/i.test(m[3]); if (pm && h < 12) h += 12; if (!pm && h === 12) h = 0; }
    return pad(h) + ':' + m[2];
  }
  window.parseAttendance = function (buf) {
    var wb = XLSX.read(buf, { type: 'array' });
    var rows = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { header: 1, raw: false, defval: '' });
    var hi = rows.findIndex(function (r) { return r.some(function (c) { return String(c).trim() === 'Employee ID'; }); });
    if (hi < 0) throw new Error('cols');
    var H = rows[hi].map(function (c) { return String(c).trim(); });
    var ix = ['Employee ID', 'First Name', 'Department', 'Date', 'First Punch', 'Last Punch'].map(function (n) { return H.indexOf(n); });
    if ([0, 1, 3, 4, 5].some(function (k) { return ix[k] < 0; })) throw new Error('cols');
    return rows.slice(hi + 1).filter(function (r) { return String(r[ix[0]]).trim() !== ''; }).map(function (r) {
      return [String(r[ix[0]]).trim(), String(r[ix[1]]).trim(), ix[2] < 0 ? 'General' : (String(r[ix[2]]).trim() || 'General'), normDate(r[ix[3]]), normTime(r[ix[4]]), normTime(r[ix[5]])];
    }).filter(function (r) { return /^\d{4}-\d{2}-\d{2}$/.test(r[3]); });
  };
})();
