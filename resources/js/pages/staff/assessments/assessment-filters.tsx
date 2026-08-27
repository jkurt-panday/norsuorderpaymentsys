"use client"

import { ColumnFilterDropdown } from "@/components/data-table/column-filter-dropdown"

interface CourseOption {
  id: number
  course_code: string
}

interface AssessmentFiltersProps {
  values: Record<string, string | undefined>
  setValue: (key: string, value: string | undefined) => void
  courseOptions: CourseOption[]
  enrolledUnderOptions: string[]
  syOptions: string[]
  semesterOptions: string[]
}

/**
 * REFACTORED: this used to be one large DropdownMenu containing all four
 * filters as grouped sections. It's now just four independent
 * ColumnFilterDropdown instances — each one small, each one only
 * responsible for a single column. This file's only job now is mapping
 * Assessments' specific option lists (courses, enrolled_under, etc.) into
 * the generic {label, value} shape ColumnFilterDropdown expects.
 *
 * Staging behavior is unchanged: every onChange call goes through
 * `setValue` (passed down from ServerDataTable), which only stores the
 * pick locally. Nothing is sent to Laravel until the shared Search
 * button/Enter fires in ServerDataTable — see server-data-table.tsx.
 */
export function AssessmentFilters({
  values,
  setValue,
  courseOptions,
  enrolledUnderOptions,
  syOptions,
  semesterOptions,
}: AssessmentFiltersProps) {
  return (
    <div className="grid grid-cols-4 items-center gap-2">
      <ColumnFilterDropdown
        label="Course"
        value={values.course_id}
        onChange={(value) => setValue("course_id", value)}
        options={courseOptions.map((course) => ({
          label: course.course_code,
          value: String(course.id),
        }))}
      />

      <ColumnFilterDropdown
        label="Enrolled Under"
        value={values.enrolled_under}
        onChange={(value) => setValue("enrolled_under", value)}
        options={enrolledUnderOptions.map((option) => ({ label: option, value: option }))}
      />

      <ColumnFilterDropdown
        label="SY Last Attended"
        value={values.sy_last_attended}
        onChange={(value) => setValue("sy_last_attended", value)}
        options={syOptions.map((option) => ({ label: option, value: option }))}
      />

      <ColumnFilterDropdown
        label="Semester"
        value={values.semester}
        onChange={(value) => setValue("semester", value)}
        options={semesterOptions.map((option) => ({ label: option, value: option }))}
      />
    </div>
  )
}