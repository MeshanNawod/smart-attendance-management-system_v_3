<?php
// admin_course_management.php
?>
<div class="card card-clean shadow-none mb-4 border-0" style="border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(161,30,23,0.07)!important;">
    <div class="card-header py-3 px-4 d-flex justify-content-between align-items-center"
         style="background:linear-gradient(135deg,#a11e17,#8b1510);border-bottom:none;">
        <strong class="text-white"><i class="bi bi-journal-bookmark-fill me-2"></i>Course Management</strong>
    </div>
    <div class="card-body p-0">
        <ul class="nav nav-tabs bg-light px-3 pt-2 border-bottom" id="courseTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="courses-tab" data-bs-toggle="tab" data-bs-target="#tab-courses" type="button" role="tab"><i class="bi bi-book me-1"></i>Courses</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="manager-tab" data-bs-toggle="tab" data-bs-target="#tab-manager" type="button" role="tab" onclick="loadCourseManager()"><i class="bi bi-folder2-open me-1"></i>Course Manager</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#tab-schedule" type="button" role="tab" onclick="loadSchedules()"><i class="bi bi-calendar-week me-1"></i>Weekly Schedule</button>
            </li>
        </ul>
        <div class="tab-content p-3" id="courseTabsContent">

            <div class="tab-pane fade show active" id="tab-courses" role="tabpanel">
                <div class="row">
                    <div class="col-md-5 border-end pe-md-4">
                        <h6 class="text-muted fw-bold mb-2"><i class="bi bi-plus-circle me-1"></i>Add New Course</h6>
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-white"><i class="bi bi-tag text-primary"></i></span>
                            <input type="text" id="new-course-code" class="form-control" placeholder="Course Code (e.g. PHY1911)">
                            <input type="text" id="new-course-name" class="form-control" placeholder="Course Name (Optional)">
                            <button class="btn btn-primary fw-semibold" onclick="addCourse()"><i class="bi bi-plus-lg me-1"></i>Add</button>
                        </div>
                        <div id="course-add-msg" class="small mb-2 min-h-xs"></div>

                        <h6 class="text-muted fw-bold mb-2 mt-3"><i class="bi bi-list-ul me-1"></i>Registered Courses</h6>
                        <div class="scroll-y-sm border rounded bg-white p-2 shadow-sm">
                            <ul id="course-list" class="list-group list-group-flush small">
                                <?php if (count($courses) > 0): ?>
                                    <?php foreach ($courses as $c): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                                        <span><strong><?php echo htmlspecialchars($c['course_code']); ?></strong><?php if (!empty($c['course_name'])): ?> - <span class="text-muted"><?php echo htmlspecialchars($c['course_name']); ?></span><?php endif; ?></span>
                                        <button class="btn btn-xs btn-outline-danger py-0 px-1" onclick="deleteCourse('<?php echo htmlspecialchars($c['course_code']); ?>')"><i class="bi bi-x-lg"></i></button>
                                    </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="list-group-item text-muted text-center py-2"><em>No courses registered</em></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="col-md-7 ps-md-4">
                        <h6 class="text-muted fw-bold mb-2"><i class="bi bi-person-plus me-1"></i>Enroll Student in Course</h6>
                        <div class="row g-2 mb-2">
                            <div class="col-5">
                                <input type="text" id="enroll-stu-course-no" class="form-control form-control-sm" placeholder="Student No (e.g. S/22/314)">
                            </div>
                            <div class="col-5">
                                <select id="enroll-stu-course-select" class="form-select form-select-sm">
                                    <option value="">Select Course...</option>
                                </select>
                            </div>
                            <div class="col-2">
                                <button class="btn btn-success btn-sm w-100 fw-semibold" onclick="enrollStudentCourse()"><i class="bi bi-check-lg"></i></button>
                            </div>
                        </div>
                        <div id="course-enroll-msg" class="small mb-2 min-h-xs"></div>

                        <h6 class="text-muted fw-bold mb-2 mt-3"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Bulk Enroll via CSV</h6>
                        <div class="card border-primary border-opacity-25 shadow-sm">
                            <div class="card-body p-2">
                                <p class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i>Name the CSV file after the course code, e.g. <code>MAT3063.csv</code> or <code>PHY1911.csv</code>. Each row: <strong>Student No</strong>, <strong>Name</strong> (name column optional).</p>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="bi bi-upload text-primary"></i></span>
                                    <input type="file" id="course-bulk-csv" class="form-control" accept=".csv">
                                    <button class="btn btn-primary fw-semibold" onclick="bulkEnrollCourseCSV()"><i class="bi bi-person-plus me-1"></i>Enroll All</button>
                                </div>
                                <div id="course-bulk-log" class="small mt-2 min-h-xs"></div>
                            </div>
                        </div>

                        <h6 class="text-muted fw-bold mb-2 mt-3"><i class="bi bi-people me-1"></i>Course Enrollment Lookup</h6>
                        <div class="input-group mb-2">
                            <input type="text" id="lookup-stu-course" class="form-control form-control-sm" placeholder="Student No to lookup">
                            <button class="btn btn-outline-secondary btn-sm" onclick="lookupStudentCourses()"><i class="bi bi-search me-1"></i>Lookup</button>
                        </div>
                        <div id="lookup-courses-result" class="border rounded bg-white p-2 shadow-sm small min-h-sm"></div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-manager" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-muted fw-bold mb-0"><i class="bi bi-folder2-open me-1"></i>Course Manager</h6>
                    <button class="btn btn-sm btn-outline-primary fw-semibold" onclick="loadCourseManager()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                </div>
                <div id="course-manager-container">
                    <div class="text-center py-4 text-muted"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading courses...</div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-schedule" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-muted fw-bold mb-0"><i class="bi bi-calendar-week me-1"></i>Weekly Course Schedule</h6>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-success fw-semibold" onclick="loadSchedules()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                        <button class="btn btn-sm btn-success fw-semibold" data-bs-toggle="modal" data-bs-target="#scheduleModal"><i class="bi bi-plus-lg me-1"></i>Add Time Slot</button>
                    </div>
                </div>
                <div id="timetable-container" class="table-responsive">
                    <table class="table table-bordered mb-0 text-center small" style="table-layout: fixed;">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:80px;">Time</th>
                                <th>Monday</th>
                                <th>Tuesday</th>
                                <th>Wednesday</th>
                                <th>Thursday</th>
                                <th>Friday</th>
                                <th>Saturday</th>
                            </tr>
                        </thead>
                        <tbody id="timetable-body">
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
