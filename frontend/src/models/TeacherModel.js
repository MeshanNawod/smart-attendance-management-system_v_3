export const TeacherModel = {
  getTeacherProfile: async () => {
    return new Promise((resolve) => {
      setTimeout(() => {
        resolve({ name: 'Dr. Perera', email: 'teacher@pdn.ac.lk', department: 'Computer Science' });
      }, 500);
    });
  },
  getMyCourses: async () => {
    return new Promise((resolve) => {
      setTimeout(() => {
        resolve([
          { course_code: 'CS101', course_name: 'Intro to CS' },
          { course_code: 'CS202', course_name: 'Data Structures' }
        ]);
      }, 500);
    });
  },
  getDashboardStats: async () => {
    return new Promise((resolve) => {
      setTimeout(() => {
        resolve({ totalStudents: 120, todaysScans: 45 });
      }, 500);
    });
  }
};
