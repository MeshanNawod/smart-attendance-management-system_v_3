import { useState, useEffect } from 'react';
import { TeacherModel } from '../models/TeacherModel';

export function useTeacherController() {
  const [teacher, setTeacher] = useState({ name: 'Loading...', email: '', department: '' });
  const [myCourses, setMyCourses] = useState([]);
  const [stats, setStats] = useState({ totalStudents: 0, todaysScans: 0 });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      const [profileData, coursesData, statsData] = await Promise.all([
        TeacherModel.getTeacherProfile(),
        TeacherModel.getMyCourses(),
        TeacherModel.getDashboardStats()
      ]);
      setTeacher(profileData);
      setMyCourses(coursesData);
      setStats(statsData);
      setLoading(false);
    };
    fetchData();
  }, []);

  return {
    teacher,
    myCourses,
    stats,
    loading
  };
}
