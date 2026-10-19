const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const PAGES = [
  // Public
  { name: 'login', url: 'http://localhost:8000/login.php', role: 'public' },
  { name: 'signup', url: 'http://localhost:8000/signup.php', role: 'public' },
  // Student
  { name: 'student_dashboard', url: 'http://localhost:8000/student/dashboard.php', role: 'student' },
  { name: 'student_my_tests', url: 'http://localhost:8000/student/my-tests.php', role: 'student' },
  { name: 'student_results', url: 'http://localhost:8000/student/results.php', role: 'student' },
  { name: 'student_analytics', url: 'http://localhost:8000/student/analytics.php', role: 'student' },
  { name: 'student_average_score', url: 'http://localhost:8000/student/average-score.php', role: 'student' },
  { name: 'student_evaluated', url: 'http://localhost:8000/student/evaluated.php', role: 'student' },
  { name: 'student_profile', url: 'http://localhost:8000/student/profile.php', role: 'student' },
  { name: 'student_result_details', url: 'http://localhost:8000/student/result-details.php?id=1', role: 'student' },
  { name: 'student_test_analysis', url: 'http://localhost:8000/student/test-analysis.php?id=1', role: 'student' },
  // Admin
  { name: 'admin_dashboard', url: 'http://localhost:8000/admin/dashboard.php', role: 'admin' },
  { name: 'admin_students', url: 'http://localhost:8000/admin/students.php', role: 'admin' },
  { name: 'admin_colleges', url: 'http://localhost:8000/admin/colleges.php', role: 'admin' },
  { name: 'admin_college_create', url: 'http://localhost:8000/admin/college_create.php', role: 'admin' },
  { name: 'admin_batches', url: 'http://localhost:8000/admin/batches.php', role: 'admin' },
  { name: 'admin_courses', url: 'http://localhost:8000/admin/courses.php', role: 'admin' },
  { name: 'admin_reports', url: 'http://localhost:8000/admin/reports.php', role: 'admin' },
  { name: 'admin_test_builder', url: 'http://localhost:8000/admin/test_builder.php', role: 'admin' },
  { name: 'admin_assessment_studio', url: 'http://localhost:8000/admin/assessment_studio.php', role: 'admin' },
  { name: 'admin_question_library', url: 'http://localhost:8000/admin/question_library.php', role: 'admin' },
  { name: 'admin_settings', url: 'http://localhost:8000/admin/settings.php', role: 'admin' },
];

const outputFile = process.argv[2] || 'audit_results_baseline.json';
const singlePage = process.argv[3]; // optional filter

const targetPages = singlePage ? PAGES.filter(p => p.name === singlePage) : PAGES;

const results = {};
if (fs.existsSync(outputFile)) {
  try {
    Object.assign(results, JSON.parse(fs.readFileSync(outputFile, 'utf8')));
  } catch(e) {}
}

const studentHeadersPath = path.resolve(__dirname, '../headers_student.json');
const adminHeadersPath = path.resolve(__dirname, '../headers_admin.json');

for (const p of targetPages) {
  console.log(`\n========================================`);
  console.log(`Auditing: ${p.name} (${p.url}) [${p.role}]`);
  console.log(`========================================`);

  const tmpOut = path.resolve(__dirname, `../lh_tmp_${p.name}.json`);
  let headersArg = '';
  if (p.role === 'student') {
    headersArg = `--extra-headers="${studentHeadersPath}"`;
  } else if (p.role === 'admin') {
    headersArg = `--extra-headers="${adminHeadersPath}"`;
  }

  const cmd = `npx lighthouse ${p.url} --output=json --output-path="${tmpOut}" --only-categories=performance --preset=desktop --chrome-flags="--headless=new --no-sandbox" ${headersArg} --quiet`;
  
  try {
    execSync(cmd, { stdio: 'inherit' });
    if (fs.existsSync(tmpOut)) {
      const data = JSON.parse(fs.readFileSync(tmpOut, 'utf8'));
      const cat = data.categories?.performance;
      const a = data.audits || {};
      
      const pageResult = {
        name: p.name,
        url: p.url,
        role: p.role,
        score: Math.round((cat?.score || 0) * 100),
        fcp: a['first-contentful-paint']?.displayValue || 'N/A',
        lcp: a['largest-contentful-paint']?.displayValue || 'N/A',
        tbt: a['total-blocking-time']?.displayValue || 'N/A',
        cls: a['cumulative-layout-shift']?.displayValue || 'N/A',
        si: a['speed-index']?.displayValue || 'N/A',
        renderBlocking: (a['render-blocking-resources']?.details?.items || []).map(i => i.url),
        totalByteWeight: a['total-byte-weight']?.displayValue || 'N/A',
      };
      
      results[p.name] = pageResult;
      console.log(`Result: Score=${pageResult.score}, FCP=${pageResult.fcp}, LCP=${pageResult.lcp}, TBT=${pageResult.tbt}, CLS=${pageResult.cls}, SI=${pageResult.si}`);
      
      // Save incrementally
      fs.writeFileSync(outputFile, JSON.stringify(results, null, 2));
      try { fs.unlinkSync(tmpOut); } catch(e) {}
    }
  } catch (err) {
    console.error(`Failed to audit ${p.name}:`, err.message);
  }
}

console.log(`\nAudit completed. Saved to ${outputFile}`);
