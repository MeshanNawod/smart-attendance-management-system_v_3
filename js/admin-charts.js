/* ─── ADMIN CHARTS REDESIGNED ────────────────────────────────────────── */
let chartInstances = {};

window.printChart = function(canvasId, title) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    var w = window.open('', '_blank', 'width=900,height=700');
    w.document.write('<html><head><title>' + title + '</title>');
    w.document.write('<style>body{text-align:center;padding:40px;font-family:"Plus Jakarta Sans","Inter",sans-serif;color:#1e293b;background:#f8fafc;}h2{font-size:22px;font-weight:700;margin-bottom:8px;}p{font-size:14px;color:#64748b;margin-bottom:30px;}img{max-width:100%;height:auto;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1),0 10px 10px -5px rgba(0,0,0,0.04);}@media print{body{padding:10px;background:#fff;}h2{font-size:18px;margin-bottom:5px;}p{margin-bottom:15px;}img{box-shadow:none;border:none;}}</style>');
    w.document.write('</head><body>');
    w.document.write('<h2>' + title + '</h2>');
    w.document.write('<p>Sentinel Swarm AMS — Generated on ' + new Date().toLocaleString() + '</p>');
    w.document.write('<img src="' + canvas.toDataURL('image/png') + '">');
    w.document.write('</body></html>');
    w.document.close();
    setTimeout(function() { w.print(); }, 400);
};

// Helper to create gradients for charts
function getChartGradient(ctx, colorStart, colorEnd) {
    if (!ctx) return 'rgba(0,0,0,0.05)';
    const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.clientHeight || 200);
    gradient.addColorStop(0, colorStart);
    gradient.addColorStop(1, colorEnd);
    return gradient;
}

async function loadOverallSummary(days) {
    try {
        const r = await fetch(`/csc2052/api/charts.php?action=overall_summary&days=${days}`);
        const d = await r.json();
        if (d.error) return;
        
        // Animated counter transition
        animateCounter('summary-total', d.total_attendance ?? 0);
        animateCounter('summary-today', d.today_attendance ?? 0);
        animateCounter('summary-students', d.unique_students ?? 0);
        animateCounter('summary-courses', d.unique_courses ?? 0);
    } catch (e) { console.error('[Charts] Overall summary:', e); }
}

function animateCounter(id, targetVal) {
    const el = document.getElementById(id);
    if (!el) return;
    const startVal = parseInt(el.textContent.replace(/[^\d]/g, '')) || 0;
    if (startVal === targetVal) {
        el.textContent = targetVal;
        return;
    }
    const duration = 800; // ms
    const startTime = performance.now();
    
    function update(now) {
        const elapsed = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        // Ease out quad
        const ease = progress * (2 - progress);
        const currentVal = Math.round(startVal + (targetVal - startVal) * ease);
        el.textContent = currentVal;
        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            el.textContent = targetVal;
        }
    }
    requestAnimationFrame(update);
}

async function loadHallOccupancyChart() {
    const days = document.getElementById('chart-days')?.value || 30;
    const selectEl = document.getElementById('occupancy-hall-select');
    const selectedHall = selectEl?.value || '';

    try {
        const r = await fetch(`/csc2052/api/charts.php?action=lecture_hall_occupancy&device=${encodeURIComponent(selectedHall)}&days=${days}`);
        const d = await r.json();

        if (d.error) return;

        // Populate dropdown if empty or has loading indicator
        if (selectEl && (selectEl.children.length <= 1 || selectEl.value === "")) {
            selectEl.innerHTML = '';
            if (d.devices && d.devices.length > 0) {
                d.devices.forEach(device => {
                    const opt = document.createElement('option');
                    opt.value = device;
                    opt.textContent = device;
                    if (device === d.selected_device) {
                        opt.selected = true;
                    }
                    selectEl.appendChild(opt);
                });
            } else {
                selectEl.innerHTML = '<option value="">No Active Halls</option>';
            }
        }

        const canvas = document.getElementById('hallOccupancyChart');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            if (chartInstances.occupancy) chartInstances.occupancy.destroy();

            // Premium gradient theme matching UoP aesthetics (Green-to-translucent)
            const occupancyGrad = getChartGradient(ctx, 'rgba(25, 135, 84, 0.25)', 'rgba(25, 135, 84, 0.0)');

            // Common premium tooltip style
            const premiumTooltip = {
                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                titleColor: '#ffffff',
                bodyColor: '#e2e8f0',
                borderColor: 'rgba(255,255,255,0.1)',
                borderWidth: 1,
                padding: 10,
                cornerRadius: 8,
                titleFont: { family: 'Plus Jakarta Sans, sans-serif', size: 12, weight: '600' },
                bodyFont: { family: 'Plus Jakarta Sans, sans-serif', size: 12 },
                displayColors: true
            };

            chartInstances.occupancy = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: 'Students Checked-in',
                        data: d.data,
                        borderColor: '#198754',
                        borderWidth: 3,
                        backgroundColor: occupancyGrad,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#198754',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: '#198754',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...premiumTooltip,
                            callbacks: {
                                label: function(ctx) { return ' ' + ctx.parsed.y + ' check-in' + (ctx.parsed.y !== 1 ? 's' : ''); }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 }, color: '#64748b' }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] },
                            ticks: {
                                stepSize: 1,
                                precision: 0,
                                font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 },
                                color: '#64748b'
                            }
                        }
                    }
                }
            });
        }
    } catch (e) {
        console.error('[Charts] Lecture Hall Occupancy error:', e);
    }
}
window.loadHallOccupancyChart = loadHallOccupancyChart;

async function loadCharts() {
    const days = document.getElementById('chart-days')?.value || 30;

    loadOverallSummary(days);
    loadHallOccupancyChart();

    // Common premium tooltip style
    const premiumTooltip = {
        backgroundColor: 'rgba(15, 23, 42, 0.95)',
        titleColor: '#ffffff',
        bodyColor: '#e2e8f0',
        borderColor: 'rgba(255,255,255,0.1)',
        borderWidth: 1,
        padding: 10,
        cornerRadius: 8,
        titleFont: { family: 'Plus Jakarta Sans, Inter, sans-serif', size: 12, weight: '600' },
        bodyFont: { family: 'Plus Jakarta Sans, Inter, sans-serif', size: 12 },
        displayColors: true
    };

    // 1. Daily trend (Line Chart)
    try {
        const r1 = await fetch(`/csc2052/api/charts.php?action=daily_trend&days=${days}`);
        const d1 = await r1.json();
        const canvas = document.getElementById('dailyTrendChart');
        if (canvas) {
            const ctx1 = canvas.getContext('2d');
            if (chartInstances.daily) chartInstances.daily.destroy();
            
            // Premium Red/Maroon theme gradient
            const bgGrad = getChartGradient(ctx1, 'rgba(161, 30, 23, 0.25)', 'rgba(161, 30, 23, 0.0)');
            
            chartInstances.daily = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: d1.labels,
                    datasets: [{
                        label: 'Check-ins',
                        data: d1.data,
                        borderColor: '#a11e17',
                        borderWidth: 3,
                        backgroundColor: bgGrad,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#a11e17',
                        pointBorderWidth: 2,
                        pointHoverBackgroundColor: '#a11e17',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...premiumTooltip,
                            callbacks: {
                                label: function(ctx) { return ' ' + ctx.parsed.y + ' check-in' + (ctx.parsed.y !== 1 ? 's' : ''); }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 }, color: '#64748b' }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] },
                            ticks: {
                                stepSize: 1,
                                precision: 0,
                                font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 },
                                color: '#64748b'
                            }
                        }
                    }
                }
            });
        }
    } catch (e) { console.error('[Charts] Daily trend:', e); }

    // 2. Modality pie (Doughnut Chart)
    try {
        const r2 = await fetch(`/csc2052/api/charts.php?action=modality_distribution&days=${days}`);
        const d2 = await r2.json();
        const canvas = document.getElementById('modalityChart');
        if (canvas) {
            if (chartInstances.modality) chartInstances.modality.destroy();
            chartInstances.modality = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: d2.labels,
                    datasets: [{
                        data: d2.data,
                        backgroundColor: d2.colors,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 8,
                                padding: 15,
                                font: { family: 'Plus Jakarta Sans, sans-serif', size: 11, weight: '500' },
                                color: '#334155'
                            }
                        },
                        tooltip: premiumTooltip
                    }
                }
            });
        }
    } catch (e) { console.error('[Charts] Modality:', e); }

    // 3. Overall Place Wise (Horizontal Bar Chart)
    try {
        const rPlace = await fetch(`/csc2052/api/charts.php?action=place_distribution&days=${days}`);
        const dPlace = await rPlace.json();
        const canvas = document.getElementById('placeChart');
        if (canvas) {
            if (chartInstances.place) chartInstances.place.destroy();
            chartInstances.place = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: dPlace.labels,
                    datasets: [{
                        label: 'Check-ins',
                        data: dPlace.data,
                        backgroundColor: dPlace.colors,
                        borderRadius: 6,
                        borderWidth: 0,
                        barThickness: 15,
                        maxBarThickness: 25
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: premiumTooltip
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] },
                            ticks: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 }, color: '#64748b' }
                        },
                        y: {
                            grid: { display: false },
                            ticks: {
                                font: { family: 'Plus Jakarta Sans, sans-serif', size: 11, weight: '500' },
                                color: '#334155'
                            }
                        }
                    }
                }
            });
        }
    } catch (e) { console.error('[Charts] Place wise:', e); }

    // 4. Course distribution (Horizontal Bar Chart)
    try {
        const r3 = await fetch(`/csc2052/api/charts.php?action=course_distribution&days=${days}`);
        const d3 = await r3.json();
        const canvas = document.getElementById('courseChart');
        if (canvas) {
            if (chartInstances.course) chartInstances.course.destroy();
            chartInstances.course = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: d3.labels,
                    datasets: [{
                        label: 'Check-ins',
                        data: d3.data,
                        backgroundColor: d3.colors,
                        borderRadius: 6,
                        borderWidth: 0,
                        barThickness: 15,
                        maxBarThickness: 25
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: premiumTooltip
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] },
                            ticks: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 }, color: '#64748b' }
                        },
                        y: {
                            grid: { display: false },
                            ticks: {
                                font: { family: 'Plus Jakarta Sans, sans-serif', size: 11, weight: '500' },
                                color: '#334155'
                            }
                        }
                    }
                }
            });
        }
    } catch (e) { console.error('[Charts] Course:', e); }

    // 5. Hourly pattern (Vertical Bar Chart)
    try {
        const r4 = await fetch(`/csc2052/api/charts.php?action=hourly_heat&days=${days}`);
        const d4 = await r4.json();
        const canvas = document.getElementById('hourlyChart');
        if (canvas) {
            const ctx4 = canvas.getContext('2d');
            if (chartInstances.hourly) chartInstances.hourly.destroy();
            
            // Sleek violet/indigo gradient for hourly distribution
            const hourlyGrad = getChartGradient(ctx4, '#6366f1', 'rgba(99, 102, 241, 0.2)');

            chartInstances.hourly = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: d4.labels,
                    datasets: [{
                        label: 'Check-ins',
                        data: d4.data,
                        backgroundColor: hourlyGrad,
                        hoverBackgroundColor: '#4f46e5',
                        borderRadius: 5,
                        borderWidth: 0,
                        barPercentage: 0.75
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: premiumTooltip
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { family: 'Plus Jakarta Sans, sans-serif', size: 9 },
                                color: '#64748b',
                                maxRotation: 45,
                                minRotation: 45
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] },
                            ticks: {
                                stepSize: 1,
                                precision: 0,
                                font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 },
                                color: '#64748b'
                            }
                        }
                    }
                }
            });
        }
    } catch (e) { console.error('[Charts] Hourly:', e); }

    // 6. Teacher chart (Horizontal Bar Chart)
    try {
        const r5 = await fetch(`/csc2052/api/charts.php?action=per_teacher&days=${days}`);
        const d5 = await r5.json();
        const canvas = document.getElementById('teacherChart');
        if (canvas) {
            if (chartInstances.teacher) chartInstances.teacher.destroy();
            if (d5.labels && d5.labels.length) {
                chartInstances.teacher = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: d5.labels,
                        datasets: [{
                            label: 'Check-ins',
                            data: d5.data,
                            backgroundColor: d5.colors,
                            borderRadius: 6,
                            borderWidth: 0,
                            barThickness: 15,
                            maxBarThickness: 25
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: { display: false },
                            tooltip: premiumTooltip
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] },
                                ticks: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 }, color: '#64748b' }
                            },
                            y: {
                                grid: { display: false },
                                ticks: {
                                    font: { family: 'Plus Jakarta Sans, sans-serif', size: 11, weight: '500' },
                                    color: '#334155'
                                }
                            }
                        }
                    }
                });
            }
        }
    } catch (e) { console.error('[Charts] Teacher:', e); }
}

window.loadStudentChart = async function() {
    const studentNo = document.getElementById('chart-student-no')?.value.trim();
    if (!studentNo || studentNo.length < 2) { document.getElementById('chart-student-no')?.focus(); return; }
    const nameEl = document.getElementById('student-chart-name');
    try {
        const days = document.getElementById('chart-days')?.value || 30;
        const r = await fetch(`/csc2052/api/charts.php?action=student_trend&student_no=${encodeURIComponent(studentNo)}&days=${days}`);
        const d = await r.json();
        const canvas = document.getElementById('studentChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        if (chartInstances.student) chartInstances.student.destroy();
        if (nameEl) nameEl.textContent = 'Student No: ' + studentNo;
        
        const grad = getChartGradient(ctx, '#0ea5e9', 'rgba(14, 165, 233, 0.1)');
        
        chartInstances.student = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: d.labels || [],
                datasets: [{
                    label: 'Check-ins',
                    data: d.data || [],
                    backgroundColor: grad,
                    hoverBackgroundColor: '#0284c7',
                    borderRadius: 6,
                    borderWidth: 0,
                    barThickness: 16
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleFont: { family: 'Plus Jakarta Sans, sans-serif', size: 12, weight: '600' },
                        bodyFont: { family: 'Plus Jakarta Sans, sans-serif', size: 12 },
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) { return ' ' + ctx.parsed.y + ' check-in' + (ctx.parsed.y !== 1 ? 's' : ''); }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 9 }, color: '#64748b' }
                    },
                    y: {
                        beginAtZero: true,
                        max: Math.max(...(d.data || [0]), 1) + 1,
                        grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] },
                        ticks: { stepSize: 1, precision: 0, font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 }, color: '#64748b' }
                    }
                }
            }
        });
    } catch (e) { console.error('[Charts] Student:', e); if (nameEl) nameEl.textContent = ''; }
};

window.loadCourseDetailChart = async function() {
    const course = document.getElementById('chart-course-input')?.value || '';
    if (!course) return;
    const titleEl = document.getElementById('course-detail-title');
    try {
        const days = document.getElementById('chart-days')?.value || 30;
        const r = await fetch(`/csc2052/api/charts.php?action=per_course&course=${encodeURIComponent(course)}&days=${days}`);
        const d = await r.json();
        const canvas = document.getElementById('courseDetailChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        if (chartInstances.courseDetail) chartInstances.courseDetail.destroy();
        if (titleEl) titleEl.textContent = 'Course: ' + course;
        
        const grad = getChartGradient(ctx, 'rgba(59, 130, 246, 0.3)', 'rgba(59, 130, 246, 0.0)');
        
        chartInstances.courseDetail = new Chart(canvas, {
            type: 'line',
            data: {
                labels: d.labels || [],
                datasets: [{
                    label: 'Check-ins',
                    data: d.data || [],
                    borderColor: '#3b82f6',
                    borderWidth: 3,
                    backgroundColor: grad,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#3b82f6',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleFont: { family: 'Plus Jakarta Sans, sans-serif', size: 12, weight: '600' },
                        bodyFont: { family: 'Plus Jakarta Sans, sans-serif', size: 12 },
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) { return ' ' + ctx.parsed.y + ' check-in' + (ctx.parsed.y !== 1 ? 's' : ''); }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 9 }, color: '#64748b' }
                    },
                    y: {
                        beginAtZero: true,
                        max: Math.max(...(d.data || [0]), 1) + 1,
                        grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] },
                        ticks: { stepSize: 1, precision: 0, font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 }, color: '#64748b' }
                    }
                }
            }
        });
    } catch (e) { console.error('[Charts] Course detail:', e); if (titleEl) titleEl.textContent = ''; }
};

/* ─── ADVANCED INTERACTIVE PEER ANALYZER ENGINE ─────────────────────── */
let advancedAnalyticsData = null;
let currentAnalyzerChartType = 'bar';

window.setAnalyzerChartType = function(type) {
    currentAnalyzerChartType = type;
    
    // Highlight toggled item
    document.querySelectorAll('.btn-toggle-item').forEach(btn => btn.classList.remove('active'));
    const targetBtn = document.getElementById('btn-toggle-' + type);
    if (targetBtn) targetBtn.classList.add('active');
    
    if (advancedAnalyticsData) {
        renderAdvancedChart(type, advancedAnalyticsData);
    }
};

window.loadAdvancedAnalytics = async function() {
    const studentNo = document.getElementById('analyzer-student-input')?.value || document.getElementById('analyzer-student-search')?.value.trim();
    const courseCode = document.getElementById('analyzer-course-input')?.value || document.getElementById('analyzer-course-search')?.value.trim();

    if (!studentNo || !courseCode) {
        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: 'Selection Required',
                text: 'Please select both a student and a course to generate advanced peer analytics.',
                confirmButtonColor: '#a11e17'
            });
        } else {
            alert('Please select both a student and a course to generate advanced peer analytics.');
        }
        return;
    }

    try {
        const r = await fetch(`/csc2052/api/charts.php?action=student_course_stats&student_no=${encodeURIComponent(studentNo)}&course_code=${encodeURIComponent(courseCode)}`);
        const data = await r.json();
        
        if (data.error) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Query Failed',
                    text: data.error,
                    confirmButtonColor: '#a11e17'
                });
            } else {
                alert('Query Failed: ' + data.error);
            }
            return;
        }

        // Cache statistical data for fast toggles
        advancedAnalyticsData = data;

        // Reveal container
        const outputContainer = document.getElementById('analyzer-output-container');
        if (outputContainer) outputContainer.classList.remove('d-none');

        // Smooth Count Animation
        animateCounter('analyzer-val-rate', data.student_rate);
        setTimeout(() => {
            const valRateEl = document.getElementById('analyzer-val-rate');
            if (valRateEl && !valRateEl.textContent.includes('%')) valRateEl.textContent += '%';
        }, 850);
        
        document.getElementById('analyzer-val-sessions').innerHTML = `Attended <b>${Math.round(data.total_sessions * data.student_rate / 100)}</b> of <b>${data.total_sessions}</b> Sessions`;

        animateCounter('analyzer-val-mean', data.class_mean);
        setTimeout(() => {
            const valMeanEl = document.getElementById('analyzer-val-mean');
            if (valMeanEl && !valMeanEl.textContent.includes('%')) valMeanEl.textContent += '%';
        }, 850);
        
        document.getElementById('analyzer-val-median').textContent = `Median: ${data.class_median}%`;

        animateCounter('analyzer-val-std', data.std_dev);
        document.getElementById('analyzer-val-minmax').textContent = `Min: ${data.class_min}% | Max: ${data.class_max}%`;

        const zBadge = data.z_score >= 0 ? 'analyzer-zbadge-positive' : 'analyzer-zbadge-negative';
        const zSign = data.z_score > 0 ? '+' : '';
        document.getElementById('analyzer-val-zscore').innerHTML = `<span class="${zBadge}">Z-score: ${zSign}${data.z_score}</span>`;
        
        const rankSuffix = getOrdinalSuffix(data.rank);
        document.getElementById('analyzer-val-rank').innerHTML = `<i class="bi bi-award me-1"></i>Rank ${data.rank}<sup>${rankSuffix}</sup> <span style="font-size:0.75rem;font-weight:600;" class="text-muted">of ${data.total_students}</span>`;

        // Interactive title
        document.getElementById('analyzer-chart-title').textContent = `${data.student_name} comparative metrics - ${courseCode}`;

        // Render comparative chart
        renderAdvancedChart(currentAnalyzerChartType, data);

        // Generate automated assessment text
        generateAssessmentReport(data);

    } catch (e) {
        console.error('[Charts] Advanced Analytics error:', e);
    }
};

function getOrdinalSuffix(i) {
    var j = i % 10, k = i % 100;
    if (j == 1 && k != 11) return "st";
    if (j == 2 && k != 12) return "nd";
    if (j == 3 && k != 13) return "rd";
    return "th";
}

function generateAssessmentReport(d) {
    const el = document.getElementById('analyzer-assessment-text');
    if (!el) return;

    let text = `<b>Statistical Summary:</b> student <b>${escapeHtml(d.student_name)}</b> exhibits an attendance rate of <b>${d.student_rate}%</b> across <b>${d.total_sessions}</b> sessions in course <b>${d.total_sessions > 0 ? '' : ''}${advancedAnalyticsData.student_name ? '' : ''}</b>. `;
    
    // Performance vs Peers
    const diff = d.student_rate - d.class_mean;
    if (diff > 15) {
        text += `This is <b>${Math.abs(diff).toFixed(1)}% above average</b>, representing excellent performance. `;
    } else if (diff < -15) {
        text += `This is <b>${Math.abs(diff).toFixed(1)}% below the class average</b>. Attendance is currently at risk. `;
    } else {
        text += `This is close to the peer average of <b>${d.class_mean}%</b> (+/- 15%). `;
    }

    // Z-score description
    if (d.z_score > 0.8) {
        text += `The student ranks in the <b>top-tier percentile</b> of this course (Z-score = +${d.z_score}). `;
    } else if (d.z_score < -0.8) {
        text += `The student stands <b>${Math.abs(d.z_score)} standard deviations below average</b>, signaling critical attendance warnings (Z-score = ${d.z_score}). `;
    } else {
        text += `The student falls within the normal bell curve distribution (Z-score = ${d.z_score}). `;
    }

    // Attendance compliance recommendation
    if (d.student_rate >= 80) {
        text += `Status: <b>Compliant (Excellent)</b>. Minimal intervention needed.`;
    } else if (d.student_rate >= 50) {
        text += `Status: <b>Compliant (Warning)</b>. Recommend monitoring.`;
    } else {
        text += `Status: <b>Non-Compliant (Critically Low)</b>. Action required. Recommend sending an attendance alert.`;
    }

    el.innerHTML = text;
}

function renderAdvancedChart(type, d) {
    const canvas = document.getElementById('advancedAnalyticsChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    
    if (chartInstances.advancedAnalyzer) {
        chartInstances.advancedAnalyzer.destroy();
    }

    const premiumTooltip = {
        backgroundColor: 'rgba(15, 23, 42, 0.95)',
        titleColor: '#ffffff',
        bodyColor: '#e2e8f0',
        borderColor: 'rgba(255,255,255,0.1)',
        borderWidth: 1,
        padding: 10,
        cornerRadius: 8,
        titleFont: { family: 'Plus Jakarta Sans, sans-serif', size: 12, weight: '600' },
        bodyFont: { family: 'Plus Jakarta Sans, sans-serif', size: 12 },
        displayColors: true
    };

    if (type === 'bar') {
        // Session turnout timeline
        chartInstances.advancedAnalyzer = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: d.timeline.labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Student Status',
                        data: d.timeline.student_presence.map(p => p * 100), // 100 for present, 0 for absent
                        backgroundColor: d.timeline.student_presence.map(p => p ? 'rgba(25, 135, 84, 0.75)' : 'rgba(220, 53, 69, 0.75)'),
                        borderColor: d.timeline.student_presence.map(p => p ? '#198754' : '#dc3545'),
                        borderWidth: 1.5,
                        borderRadius: 4,
                        barThickness: 15
                    },
                    {
                        type: 'line',
                        label: 'Class Turnout Rate',
                        data: d.timeline.class_turnout,
                        borderColor: '#6366f1',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.2,
                        pointRadius: 3,
                        pointBackgroundColor: '#ffffff'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 } }
                    },
                    tooltip: {
                        ...premiumTooltip,
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return context.parsed.y === 100 ? ' Student: Present' : ' Student: Absent';
                                } else {
                                    return ` Class Turnout: ${context.parsed.y}%`;
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 9 }, color: '#64748b' }
                    },
                    y: {
                        beginAtZero: true,
                        max: 105,
                        grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] },
                        ticks: {
                            font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 },
                            color: '#64748b',
                            callback: function(value) { return value + '%'; }
                        }
                    }
                }
            }
        });

    } else if (type === 'line') {
        // Cumulative turnout comparison over timeline
        const gradStu = getChartGradient(ctx, 'rgba(161, 30, 23, 0.2)', 'rgba(161, 30, 23, 0.0)');
        const gradClass = getChartGradient(ctx, 'rgba(59, 130, 246, 0.1)', 'rgba(59, 130, 246, 0.0)');

        chartInstances.advancedAnalyzer = new Chart(canvas, {
            type: 'line',
            data: {
                labels: d.timeline.labels,
                datasets: [
                    {
                        label: 'Student Running Rate',
                        data: d.timeline.student_cumulative,
                        borderColor: '#a11e17',
                        backgroundColor: gradStu,
                        fill: true,
                        tension: 0.3,
                        borderWidth: 3,
                        pointRadius: 3,
                        pointBackgroundColor: '#ffffff'
                    },
                    {
                        label: 'Class Turnout Running Rate',
                        data: d.timeline.class_cumulative,
                        borderColor: '#3b82f6',
                        backgroundColor: gradClass,
                        fill: true,
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: 2,
                        pointBackgroundColor: '#ffffff'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 } }
                    },
                    tooltip: premiumTooltip
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 9 }, color: '#64748b' }
                    },
                    y: {
                        beginAtZero: true,
                        max: 105,
                        grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] },
                        ticks: {
                            font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 },
                            color: '#64748b',
                            callback: function(value) { return value + '%'; }
                        }
                    }
                }
            }
        });

    } else if (type === 'radar') {
        // Modality comparison radar chart
        chartInstances.advancedAnalyzer = new Chart(canvas, {
            type: 'radar',
            data: {
                labels: d.modality.labels,
                datasets: [
                    {
                        label: 'Student Modality Distribution',
                        data: d.modality.student_data,
                        borderColor: '#a11e17',
                        backgroundColor: 'rgba(161, 30, 23, 0.15)',
                        borderWidth: 2.5,
                        pointRadius: 3
                    },
                    {
                        label: 'Class Average Distribution',
                        data: d.modality.class_data,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 1.5,
                        pointRadius: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 } }
                    },
                    tooltip: premiumTooltip
                },
                scales: {
                    r: {
                        angleLines: { color: 'rgba(226, 232, 240, 0.8)' },
                        grid: { color: 'rgba(226, 232, 240, 0.8)' },
                        pointLabels: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 10, weight: '600' } },
                        ticks: { display: false }
                    }
                }
            }
        });

    } else if (type === 'distribution') {
        // Peer peer attendance distribution curve
        const bins = Object.keys(d.distribution);
        const counts = Object.values(d.distribution);
        
        // Find student bin to color highlight
        const studentRate = d.student_rate;
        let activeBinIndex = -1;
        if (studentRate <= 20) activeBinIndex = 0;
        else if (studentRate <= 40) activeBinIndex = 1;
        else if (studentRate <= 60) activeBinIndex = 2;
        else if (studentRate <= 80) activeBinIndex = 3;
        else activeBinIndex = 4;

        const colors = counts.map((c, i) => i === activeBinIndex ? '#a11e17' : '#e2e8f0');
        const hoverColors = counts.map((c, i) => i === activeBinIndex ? '#85130d' : '#cbd5e1');

        chartInstances.advancedAnalyzer = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: bins,
                datasets: [{
                    label: 'Number of Students',
                    data: counts,
                    backgroundColor: colors,
                    hoverBackgroundColor: hoverColors,
                    borderRadius: 6,
                    borderWidth: 0,
                    barPercentage: 0.7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...premiumTooltip,
                        callbacks: {
                            afterLabel: function(context) {
                                if (context.dataIndex === activeBinIndex) {
                                    return ` Target Student (${d.student_rate}%) is in this bracket.`;
                                }
                                return '';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans, sans-serif', size: 10, weight: '600' }, color: '#334155' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(226, 232, 240, 0.6)', borderDash: [4, 4] },
                        ticks: {
                            stepSize: 1,
                            precision: 0,
                            font: { family: 'Plus Jakarta Sans, sans-serif', size: 10 },
                            color: '#64748b'
                        }
                    }
                }
            }
        });
    }
}
