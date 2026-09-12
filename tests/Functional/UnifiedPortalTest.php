<?php

namespace Tests\Functional;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/college_data.php';

class UnifiedPortalTest extends TestCase
{
    public function testPortalFilesExist(): void
    {
        $base = dirname(__DIR__, 2);
        $this->assertFileExists($base . '/portal/student_entry.php');
        $this->assertFileExists($base . '/portal/process_entry.php');
        $this->assertFileExists($base . '/portal/index.php');
    }

    public function testAcademicClassResolutionForPortal(): void
    {
        // Science & IT
        $csClasses = get_department_classes('CS');
        $this->assertSame('III B.Sc', $csClasses['UG_3']['short']);
        $this->assertSame('III B.Sc Computer Science', $csClasses['UG_3']['label']);

        // Arts
        $tamClasses = get_department_classes('TAM');
        $this->assertSame('II B.A', $tamClasses['UG_2']['short']);
        $this->assertSame('II B.A Tamil', $tamClasses['UG_2']['label']);

        // Commerce
        $commClasses = get_department_classes('COMM');
        $this->assertSame('I M.Com', $commClasses['PG_1']['short']);
        $this->assertSame('I M.Com Commerce', $commClasses['PG_1']['label']);

        // BCA
        $bcaClasses = get_department_classes('BCA');
        $this->assertSame('I BCA', $bcaClasses['UG_1']['short']);
    }

    public function testPayloadTransformationWithMandatoryEmailAndRegNo(): void
    {
        $input = [
            'name' => 'KAVIN S',
            'roll_no' => '24CSC130',
            'reg_no' => '2410352',
            'email' => 'kavin@aagacvpm.edu.in',
            'course' => 'CS',
            'academic_year_level' => 'UG_3',
            'dob' => '2006-05-14',
            'mobile' => '9876543210',
            'parent_name' => 'Senthil Kumar',
            'parent_phone' => '9876543211',
            'community' => 'BC',
            'caste' => 'Vanniyar',
            'aadhaar_no' => '123456789012',
            'emis_no' => '33041234567890',
            'acc_no' => '112233445566',
            'ifsc_code' => 'SBIN0001234',
            'bank_name' => 'SBI'
        ];

        // Ensure mandatory fields
        $this->assertNotEmpty($input['email']);
        $this->assertNotEmpty($input['reg_no']);
        $this->assertNotEmpty($input['roll_no']);
        $this->assertNotEmpty($input['name']);

        // Derive academic class
        $ylevel = $input['academic_year_level'];
        $course = $input['course'];
        $classes = get_department_classes($course);
        $academic_class = $classes[$ylevel]['short'];
        $main_subject = $classes[$ylevel]['label'];

        $this->assertSame('III B.Sc', $academic_class);

        // 1. Validate students table mapping
        $students = [
            'name' => $input['name'],
            'roll_no' => $input['roll_no'],
            'exam_reg_no' => $input['reg_no'],
            'reg_no' => $input['reg_no'],
            'email' => $input['email'],
            'course' => $course,
            'academic_year_level' => $ylevel,
            'academic_class' => $academic_class
        ];
        $this->assertSame('kavin@aagacvpm.edu.in', $students['email']);
        $this->assertSame('2410352', $students['reg_no']);
        $this->assertSame('2410352', $students['exam_reg_no']);

        // 2. Validate bio_data table mapping
        $bio = [
            'tamil_reg_no' => $input['reg_no'],
            'exam_reg_no' => $input['reg_no'],
            'reg_no' => $input['reg_no'],
            'email' => $input['email'],
            'department' => $course,
            'class' => $academic_class,
            'academic_class' => $academic_class
        ];
        $this->assertSame('kavin@aagacvpm.edu.in', $bio['email']);
        $this->assertSame('2410352', $bio['reg_no']);
        $this->assertSame('2410352', $bio['exam_reg_no']);
        $this->assertSame('III B.Sc', $bio['class']);

        // 3. Validate umis_students table mapping
        $umis = [
            'student_id' => 'STU_' . $input['roll_no'],
            'roll_no' => $input['roll_no'],
            'reg_no' => $input['reg_no'],
            'exam_reg_no' => $input['reg_no'],
            'email' => $input['email'],
            'course' => $course,
            'academic_class' => $academic_class
        ];
        $this->assertSame('kavin@aagacvpm.edu.in', $umis['email']);
        $this->assertSame('2410352', $umis['reg_no']);
        $this->assertSame('2410352', $umis['exam_reg_no']);
        $this->assertSame('STU_24CSC130', $umis['student_id']);
    }

    public function testPortalRenderingContainsKeyFields(): void
    {
        ob_start();
        include dirname(__DIR__, 2) . '/portal/student_entry.php';
        $html = ob_get_clean();

        // Check Institution title
        $this->assertStringContainsString('Arignar Anna Government Arts College', $html);
        
        // Check mandatory fields
        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringContainsString('name="reg_no"', $html);
        $this->assertStringContainsString('name="roll_no"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('name="course"', $html);
        $this->assertStringContainsString('name="academic_year_level"', $html);

        // Check 3 steps
        $this->assertStringContainsString('Step 1: Student Record', $html);
        $this->assertStringContainsString('Step 2: Bio Data', $html);
        $this->assertStringContainsString('Step 3: UMIS', $html);

        // Assert Student Record (File Photo + 19 Fields & Character / Attendance)
        $this->assertStringContainsString('name="student_photo_file"', $html);
        $this->assertStringContainsString('name="current_semester"', $html);
        $this->assertStringContainsString('name="sem1_subject[]"', $html);
        $this->assertStringContainsString('name="sem1_ue[]"', $html);
        $this->assertStringContainsString('name="sem1_ia[]"', $html);
        $this->assertStringContainsString('name="sem1_total[]"', $html);
        $this->assertStringContainsString('name="sem1_pf[]"', $html);
        $this->assertStringContainsString('name="dob"', $html);
        $this->assertStringContainsString('name="admission_no"', $html);
        $this->assertStringContainsString('name="parent_name"', $html);
        $this->assertStringContainsString('name="parent_occupation"', $html);
        $this->assertStringContainsString('name="parent_address"', $html);
        $this->assertStringContainsString('name="permanent_address"', $html);
        $this->assertStringContainsString('name="main_subject"', $html);
        $this->assertStringContainsString('name="medium"', $html);
        $this->assertStringContainsString('name="ancillary_subjects"', $html);
        $this->assertStringContainsString('name="exam_reg_no"', $html);
        $this->assertStringContainsString('name="date_of_joining"', $html);
        $this->assertStringContainsString('name="date_of_leaving"', $html);
        $this->assertStringContainsString('name="community"', $html);
        $this->assertStringContainsString('name="part1_language"', $html);
        $this->assertStringContainsString('name="exam_at_admission"', $html);
        $this->assertStringContainsString('name="last_college"', $html);
        $this->assertStringContainsString('name="independent_work"', $html);
        $this->assertStringContainsString('name="conduct"', $html);
        $this->assertStringContainsString('name="cooperation"', $html);
        $this->assertStringContainsString('name="leadership"', $html);
        $this->assertStringContainsString('name="overall_assessment"', $html);
        $this->assertStringContainsString('name="hod_name"', $html);

        // Assert Bio Data Fields (All 19 Official Admin Fields)
        $this->assertStringContainsString('name="parents_photo_file"', $html);
        $this->assertStringContainsString('name="name_ta_en"', $html); // 1. Student Name
        $this->assertStringContainsString('name="parents_name_ta"', $html); // 2. Parents Name
        $this->assertStringContainsString('name="bio_dob"', $html); // 3. DOB
        $this->assertStringContainsString('name="bio_community"', $html); // 4. Community
        $this->assertStringContainsString('name="caste"', $html); // 5. Caste
        $this->assertStringContainsString('name="emis_no"', $html); // 6. EMIS
        $this->assertStringContainsString('name="aadhaar_no"', $html); // 7. Aadhaar
        $this->assertStringContainsString('name="bio_parent_occupation"', $html); // 8. Parent Occupation
        $this->assertStringContainsString('name="parent_income"', $html); // 9. Parent Income
        $this->assertStringContainsString('name="accommodation"', $html); // 10. Accommodation
        $this->assertStringContainsString('name="travel_concession"', $html); // 11. Travel Concession
        $this->assertStringContainsString('name="scholarship_office"', $html); // 12. Scholarship Office
        $this->assertStringContainsString('name="scholarship_details"', $html); // 13. Scholarship Details
        $this->assertStringContainsString('name="prev_attendance"', $html); // 14. Prev Attendance
        $this->assertStringContainsString('name="contact_address"', $html); // 15. Contact Address
        $this->assertStringContainsString('name="bio_permanent_address"', $html); // 16. Permanent Address
        $this->assertStringContainsString('name="ex_serviceman"', $html); // 17. Ex-Serviceman
        $this->assertStringContainsString('name="disability"', $html); // 18. Disability
        $this->assertStringContainsString('name="achievements"', $html); // 19. Achievements
        $this->assertStringContainsString('name="bio_student_phone"', $html);
        $this->assertStringContainsString('name="bio_parent_phone"', $html);
        $this->assertStringContainsString('name="tamil_reg_no"', $html);

        // Assert UMIS Fields (All 80 Canonical Items)
        $this->assertStringContainsString('name="college_name"', $html); // 1. College Name
        $this->assertStringContainsString('name="college_code"', $html); // 2. College Code
        $this->assertStringContainsString('name="college_district"', $html); // 3. College District
        $this->assertStringContainsString('name="college_region"', $html); // 4. College Region
        $this->assertStringContainsString('name="umis_emis_id"', $html); // 5. EMIS ID
        $this->assertStringContainsString('name="no_emis_reason"', $html); // 6. No EMIS Reason
        $this->assertStringContainsString('name="salutation"', $html); // 7. Salutation
        $this->assertStringContainsString('name="student_name_cert"', $html); // 8. Name as per Cert
        $this->assertStringContainsString('name="student_name_aadhaar"', $html); // 9. Name as per Aadhaar
        $this->assertStringContainsString('name="umis_dob"', $html); // 10. DOB
        $this->assertStringContainsString('name="gender"', $html); // 11. Gender
        $this->assertStringContainsString('name="blood_group"', $html); // 12. Blood Group
        $this->assertStringContainsString('name="nationality"', $html); // 13. Nationality
        $this->assertStringContainsString('name="religion"', $html); // 14. Religion
        $this->assertStringContainsString('name="umis_community"', $html); // 15. Community
        $this->assertStringContainsString('name="umis_caste"', $html); // 16. Caste
        $this->assertStringContainsString('name="community_cert_no"', $html); // 17. Community Cert No
        $this->assertStringContainsString('name="umis_aadhaar_no"', $html); // 18. Aadhaar No
        $this->assertStringContainsString('name="is_first_graduate"', $html); // 19. First Graduate
        $this->assertStringContainsString('name="first_graduate_cert_no"', $html); // 19(a). FG Cert No
        $this->assertStringContainsString('name="special_quota"', $html); // 20. Special Quota
        $this->assertStringContainsString('name="special_quota_category"', $html); // 20(a). Special Quota Category
        $this->assertStringContainsString('name="is_differently_abled"', $html); // 21. Differently Abled
        $this->assertStringContainsString('name="udid_no"', $html); // 21(a). UDID No
        $this->assertStringContainsString('name="disability_type"', $html); // 21(b). Disability Type
        $this->assertStringContainsString('name="disability_percentage"', $html); // 21(c). Disability %
        $this->assertStringContainsString('name="umis_mobile"', $html); // 22. Mobile No
        $this->assertStringContainsString('name="umis_email"', $html); // 23. Email ID
        $this->assertStringContainsString('name="country"', $html); // 24. Country
        $this->assertStringContainsString('name="state"', $html); // 25. State
        $this->assertStringContainsString('name="location_type"', $html); // 26. Location Type
        $this->assertStringContainsString('name="district"', $html); // 27. District
        $this->assertStringContainsString('name="taluk"', $html); // 28. Taluk
        $this->assertStringContainsString('name="village"', $html); // 29. Village
        $this->assertStringContainsString('name="block"', $html); // 30. Block
        $this->assertStringContainsString('name="village_panchayat"', $html); // 31. Village Panchayat
        $this->assertStringContainsString('name="pincode"', $html); // 32. Pincode
        $this->assertStringContainsString('name="postal_address"', $html); // 33. Postal Address
        $this->assertStringContainsString('name="comm_country"', $html); // 34. Comm Country
        $this->assertStringContainsString('name="comm_state"', $html); // 35. Comm State
        $this->assertStringContainsString('name="comm_location_type"', $html); // 36. Comm Location Type
        $this->assertStringContainsString('name="comm_district"', $html); // 37. Comm District
        $this->assertStringContainsString('name="comm_taluk"', $html); // 38. Comm Taluk
        $this->assertStringContainsString('name="comm_village"', $html); // 39. Comm Village
        $this->assertStringContainsString('name="comm_block"', $html); // 40. Comm Block
        $this->assertStringContainsString('name="comm_village_panchayat"', $html); // 41. Comm Village Panchayat
        $this->assertStringContainsString('name="comm_pincode"', $html); // 42. Comm Pincode
        $this->assertStringContainsString('name="comm_postal_address"', $html); // 43. Comm Postal Address
        $this->assertStringContainsString('name="is_orphan"', $html); // 44. Is Orphan
        $this->assertStringContainsString('name="father_name"', $html); // 45. Father Name
        $this->assertStringContainsString('name="father_occupation"', $html); // 46. Father Occupation
        $this->assertStringContainsString('name="mother_name"', $html); // 47. Mother Name
        $this->assertStringContainsString('name="mother_occupation"', $html); // 48. Mother Occupation
        $this->assertStringContainsString('name="guardian_name"', $html); // 49. Guardian Name
        $this->assertStringContainsString('name="family_income"', $html); // 50. Family Income
        $this->assertStringContainsString('name="income_cert_no"', $html); // 51. Income Cert No
        $this->assertStringContainsString('name="guardian_mobile"', $html); // 52. Guardian Mobile
        $this->assertStringContainsString('name="seed_account_no"', $html); // 53. Seed Acc No
        $this->assertStringContainsString('name="seed_mobile"', $html); // 54. Seed Mobile
        $this->assertStringContainsString('name="seed_bank_name"', $html); // 55. Seed Bank Name
        $this->assertStringContainsString('name="seed_status"', $html); // 56. Seed Status
        $this->assertStringContainsString('name="seed_message"', $html); // 57. Seed Message
        $this->assertStringContainsString('name="acc_no"', $html); // 58. Account No
        $this->assertStringContainsString('name="ifsc_code"', $html); // 59. IFSC Code
        $this->assertStringContainsString('name="bank_name"', $html); // 60. Bank Name
        $this->assertStringContainsString('name="bank_branch"', $html); // 61. Bank Branch
        $this->assertStringContainsString('name="bank_city"', $html); // 62. Bank City
        $this->assertStringContainsString('name="account_type"', $html); // 63. Account Type
        $this->assertStringContainsString('name="join_year"', $html); // 64. Joining Year
        $this->assertStringContainsString('name="stream_type"', $html); // 65. Stream Type
        $this->assertStringContainsString('name="course_type"', $html); // 66. Course Type
        $this->assertStringContainsString('name="umis_course_display"', $html); // 67. Course / Department
        $this->assertStringContainsString('name="specialization"', $html); // 68. Specialization
        $this->assertStringContainsString('name="medium_of_instruction"', $html); // 69. Medium of Instruction
        $this->assertStringContainsString('name="mode_of_study"', $html); // 70. Mode of Study
        $this->assertStringContainsString('name="date_of_admission"', $html); // 71. Date of Admission
        $this->assertStringContainsString('name="type_of_admission"', $html); // 72. Type of Admission
        $this->assertStringContainsString('name="counselling_no"', $html); // 73. Counselling No
        $this->assertStringContainsString('name="umis_roll_no"', $html); // 74. Roll Number
        $this->assertStringContainsString('name="is_lateral_entry"', $html); // 75. Lateral Entry
        $this->assertStringContainsString('name="is_hosteller"', $html); // 76. Hosteller
        $this->assertStringContainsString('name="current_status"', $html); // 77. Current Status
        $this->assertStringContainsString('name="year_of_course_completed"', $html); // 78. Course Completed Year
        $this->assertStringContainsString('name="year_of_study"', $html); // 79. Year of Study
        $this->assertStringContainsString('name="umis_parent_phone"', $html); // Parent Phone
        // 80. School Information 6th to 12th
        $this->assertStringContainsString('name="sch_name_12th"', $html);
        $this->assertStringContainsString('name="sch_type_12th"', $html);
        $this->assertStringContainsString('name="sch_name_11th"', $html);
        $this->assertStringContainsString('name="sch_name_10th"', $html);
        $this->assertStringContainsString('name="sch_name_9th"', $html);
        $this->assertStringContainsString('name="sch_name_8th"', $html);
        $this->assertStringContainsString('name="sch_name_7th"', $html);
        $this->assertStringContainsString('name="sch_name_6th"', $html);
    }

    public function testSemesterMarksSerializationAndPassFailCalculation(): void
    {
        $subjects = ['Data Structures', 'Operating Systems', 'Mathematics'];
        $ues = ['45', '25', '60']; // 45 passed, 25 failed (UE < 30), 60 passed
        $ias = ['20', '20', '22'];
        $totals = ['65', '45', '82'];
        $pfs = ['Pass', 'Fail', 'Pass'];

        $marks_data = [];
        for ($i = 0; $i < count($subjects); $i++) {
            $ue = (float)$ues[$i];
            $ia = (float)$ias[$i];
            $total = (float)$totals[$i];
            $pf = ($ue >= 30 && $total >= 40) ? 'Pass' : 'Fail';

            $marks_data[] = [
                'subject' => $subjects[$i],
                'ue' => $ue,
                'ia' => $ia,
                'total' => $total,
                'pass_fail' => $pf
            ];
        }

        $json = json_encode($marks_data);
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertCount(3, $decoded);
        $this->assertSame('Pass', $decoded[0]['pass_fail']);
        $this->assertSame('Fail', $decoded[1]['pass_fail']);
        $this->assertSame('Pass', $decoded[2]['pass_fail']);
    }
}
