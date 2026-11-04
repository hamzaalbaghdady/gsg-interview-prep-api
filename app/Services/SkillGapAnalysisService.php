<?php

namespace App\Services;

use App\Models\JobDescription;
use App\Models\Resume;

class SkillGapAnalysisService
{
    /**
     * Compute a detailed weighted match and gap assessment between a job and a resume.
     */
    public function calculate(JobDescription $job, Resume $resume): array
    {
        // Get skills
        $resumeSkillsMap = $resume->skills->keyBy('id'); // map => O(1) access
        $jobSkills = $job->skills;

        $matching = [];
        $missing = [];
        $totalJobWeight = 0.0;
        $earnedCandidateWeight = 0.0;

        // get job required skills one by one
        foreach ($jobSkills as $jobSkill) {
            // get skill importance
            $importance = (float) ($jobSkill->pivot->importance ?? 0.00);
            $totalJobWeight += $importance;

            // get the same skill in resume-skills if any
            if ($resumeSkillsMap->has($jobSkill->id)) {
                $resumeSkill = $resumeSkillsMap->get($jobSkill->id);

                // get resume skill confidence
                $confidence = (float) ($resumeSkill->pivot->confidence_score ?? 0.00);

                // The candidate earns points based on their skill accuracy score
                $earnedCandidateWeight += ($importance * $confidence);

                $matching[] = [
                    'id' => $jobSkill->id,
                    'name' => $jobSkill->name,
                    'category' => $jobSkill->category,
                    'importance' => $importance,
                    'candidate_confidence' => $confidence,
                ];
            } else {
                $missing[] = [
                    'id' => $jobSkill->id,
                    'name' => $jobSkill->name,
                    'category' => $jobSkill->category,
                    'importance' => $importance,
                ];
            }
        }

        // Calculate match percent
        $matchPercentage = $totalJobWeight > 0 // Avoid division by zero
            ? round(($earnedCandidateWeight / $totalJobWeight) * 100, 2)
            : 0.00;

        return [
            'match_percentage' => $matchPercentage,
            'matching_skills_count' => count($matching),
            'missing_skills_count' => count($missing),
            'matching_skills' => $matching,
            'missing_skills' => $missing,
        ];
    }
}
