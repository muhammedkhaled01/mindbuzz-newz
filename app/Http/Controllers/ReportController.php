<?php

namespace App\Http\Controllers;

use App\Models\GameType;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Program;
use App\Models\School;
use App\Models\Skills;
use App\Models\Stage;
use Illuminate\Http\Request;
use App\Models\StudentTest;
use App\Models\StudentDegree;
use App\Models\Game;
use App\Models\Lesson;
use App\Models\Unit;
use App\Models\Test;
use App\Models\StudentProgress;
use App\Models\TestTypes;
use App\Models\User;
use Carbon\Carbon;
use DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['details.stage', 'userCourses.program', 'groups'])
            ->where('role', '2');

        if ($request->filled('school')) {
            $query->whereHas('details', function ($q) use ($request) {
                $q->where('school_id', $request->input('school'));
            });
        }

        if ($request->filled('program')) {
            $query->whereHas('userCourses', function ($q) use ($request) {
                $q->where('program_id', $request->input('program'));
            });
        }

        if ($request->filled('grade')) {
            $query->whereHas('details', function ($q) use ($request) {
                $q->where('stage_id', $request->input('grade'));
            });
        }

        if ($request->filled('group')) {
            $query->whereHas('groups', function ($q) use ($request) {
                $q->where('group_id', $request->input('group'));
            });
        }

        $students = $query->simplePaginate(10);

        $schools = School::all();
        $programs = Program::with('course', 'stage')->get();
        $grades = Stage::all();
        $classes = Group::all();
        $units = Unit::all();
        $lessons = Lesson::all();
        $games = Game::all();
        $skills = Skills::all();
        $testTypes = TestTypes::all();

        // Initialize $response and $data with default values
        $response = [
            'skills' => [],
            'units' => [],
            'lessons' => [],
            'games' => [],
            'tprogress' => [],
            'trials' => [],
            'skillsData' => [],
        ];

        $data = [
            'student_latest' => '',
            'counts' => [
                'completed' => 0,
                'overdue' => 0,
                'pending' => 0,
            ],
            'assignments_percentages' => [
                'completed' => 0,
                'overdue' => 0,
                'pending' => 0,
            ],
            'tests' => [],
            'test_types' => [],
            'skillsData' => [],
            'tprogress' => [],

        ];

        return view('dashboard.reports.index', compact(
            'students',
            'schools',
            'programs',
            'games',
            'response',
            'lessons',
            'units',
            'grades',
            'classes',
            'testTypes',
            'data',
            'skills'
        ));
    }

    public function completionReport(Request $request)
    {
        $studentId = $request->input('student_id');
        $programId = $request->input('program_id');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $status = $request->input('status');

        $query = StudentTest::with('tests')->where('student_id', $studentId);

        if ($fromDate && $toDate) {
            $query->whereBetween('start_date', [Carbon::parse($fromDate), Carbon::parse($toDate)]);
        }

        if ($status) {
            switch ($status) {
                case 'Completed':
                    $query->where('status', 1);
                    break;
                case 'Overdue':
                    $query->where('due_date', '<', now())->where('status', '!=', 1);
                    break;
                case 'Pending':
                    $query->where('status', 0)->where('due_date', '>=', now());
                    break;
            }
        }

        if ($programId) {
            $query->where('program_id', $programId);
        }

        $tests = $query->get();
        $totalTests = $tests->count();
        $completedTests = $tests->where('status', 1)->count();
        $overdueTests = $tests->where('due_date', '<', now())->where('status', '!=', 1)->count();
        $pendingTests = $totalTests - $completedTests - $overdueTests;

        $data = [
            'student_latest' => 'Some latest progress data', // Replace with actual latest progress data
            'counts' => [
                'completed' => $completedTests,
                'overdue' => $overdueTests,
                'pending' => $pendingTests,
            ],
            'assignments_percentages' => [
                'completed' => $totalTests > 0 ? round(($completedTests / $totalTests) * 100, 2) : 0,
                'overdue' => $totalTests > 0 ? round(($overdueTests / $totalTests) * 100, 2) : 0,
                'pending' => $totalTests > 0 ? round(($pendingTests / $totalTests) * 100, 2) : 0,
            ],
            'tests' => $tests,
        ];

        return response()->json($data);
    }

    public function masteryReport(Request $request)
    {
        $studentId = $request->input('student_id');
        $programId = $request->input('program_id');
        $filterType = $request->input('filter_type');
        $filterValue = $request->input($filterType . '_id');

        $query = StudentProgress::where('student_id', $studentId)
            ->where('program_id', $programId)
            ->where('is_done', 1);

        if ($filterType && $filterValue) {
            if ($filterType == 'game') {
                $query->whereHas('test', function ($q) use ($filterValue) {
                    $q->where('game_id', $filterValue);
                });
            } elseif ($filterType == 'skill') {
                $query->whereHas('test.game.gameTypes.skills', function ($q) use ($filterValue) {
                    $q->where('skill_id', $filterValue);
                });
            } else {
                $query->where($filterType . '_id', $filterValue);
            }
        }

        if ($request->filled(['from_date', 'to_date']) && $request->from_date != NULL && $request->to_date != NULL) {
            $from_date = Carbon::parse($request->from_date)->startOfDay();
            $to_date = Carbon::parse($request->to_date)->endOfDay();
            $query->whereBetween('created_at', [$from_date, $to_date]);
        }

        $studentProgress = $query->get();
        $unitsMastery = [];
        $lessonsMastery = [];
        $gamesMastery = [];
        $skillsMastery = [];

        foreach ($studentProgress as $progress) {
            $test = Test::with(['game.gameTypes.skills.skill'])->where('lesson_id', $progress->lesson_id)->find($progress->test_id);

            if (!$test || !$test->game || !$test->game->gameTypes) {
                continue;
            }

            $gameType = $test->game->gameTypes;

            if (!isset($unitsMastery[$progress->unit_id])) {
                $unitsMastery[$progress->unit_id] = [
                    'unit_id' => $progress->unit_id,
                    'name' => Unit::find($progress->unit_id)->name,
                    'failed' => 0,
                    'introduced' => 0,
                    'practiced' => 0,
                    'mastered' => 0,
                    'total_attempts' => 0,
                    'total_score' => 0,
                    'mastery_percentage' => 0,
                ];
            }

            if (!isset($lessonsMastery[$progress->lesson_id])) {
                $lessonsMastery[$progress->lesson_id] = [
                    'lesson_id' => $progress->lesson_id,
                    'name' => Lesson::find($progress->lesson_id)->name,
                    'failed' => 0,
                    'introduced' => 0,
                    'practiced' => 0,
                    'mastered' => 0,
                    'total_attempts' => 0,
                    'total_score' => 0,
                    'mastery_percentage' => 0,
                ];
            }

            if (!isset($gamesMastery[$test->game_id])) {
                $gamesMastery[$test->game_id] = [
                    'game_id' => $test->game_id,
                    'name' => Game::find($test->game_id)->name,
                    'failed' => 0,
                    'introduced' => 0,
                    'practiced' => 0,
                    'mastered' => 0,
                    'total_attempts' => 0,
                    'total_score' => 0,
                    'mastery_percentage' => 0,
                ];
            }

            if (!isset($skillsMastery[$gameType->id])) {
                $skillsMastery[$gameType->id] = [
                    'skill_id' => $gameType->id,
                    'name' => GameType::find($gameType->id)->name,
                    'failed' => 0,
                    'introduced' => 0,
                    'practiced' => 0,
                    'mastered' => 0,
                    'total_attempts' => 0,
                    'total_score' => 0,
                    'mastery_percentage' => 0,
                ];
            }

            foreach ($gameType->skills->unique() as $gameSkill) {
                $skill = $gameSkill->skill;

                if (!isset($skillsMastery[$skill->id])) {
                    $skillsMastery[$skill->id] = [
                        'skill_id' => $skill->id,
                        'name' => $skill->skill,
                        'failed' => 0,
                        'introduced' => 0,
                        'practiced' => 0,
                        'mastered' => 0,
                        'total_attempts' => 0,
                        'total_score' => 0,
                        'mastery_percentage' => 0,
                    ];
                }

                $skillsMastery[$skill->id]['total_attempts']++;
                if ($progress->is_done) {
                    if ($progress->score >= 80) {
                        $skillsMastery[$skill->id]['mastered']++;
                    } elseif ($progress->score >= 60) {
                        $skillsMastery[$skill->id]['practiced']++;
                    } elseif ($progress->score >= 30) {
                        $skillsMastery[$skill->id]['introduced']++;
                    } else {
                        $skillsMastery[$skill->id]['failed']++;
                    }
                } else {
                    $skillsMastery[$skill->id]['failed']++;
                }
                $skillsMastery[$skill->id]['total_score'] += $progress->score;
            }

            $unitsMastery[$progress->unit_id]['total_attempts']++;
            $lessonsMastery[$progress->lesson_id]['total_attempts']++;
            $gamesMastery[$test->game_id]['total_attempts']++;

            if ($progress->is_done) {
                if ($progress->score >= 80) {
                    $unitsMastery[$progress->unit_id]['mastered']++;
                    $lessonsMastery[$progress->lesson_id]['mastered']++;
                    $gamesMastery[$test->game_id]['mastered']++;
                } elseif ($progress->score >= 60) {
                    $unitsMastery[$progress->unit_id]['practiced']++;
                    $lessonsMastery[$progress->lesson_id]['practiced']++;
                    $gamesMastery[$test->game_id]['practiced']++;
                } elseif ($progress->score >= 30) {
                    $unitsMastery[$progress->unit_id]['introduced']++;
                    $lessonsMastery[$progress->lesson_id]['introduced']++;
                    $gamesMastery[$test->game_id]['introduced']++;
                } else {
                    $unitsMastery[$progress->unit_id]['failed']++;
                    $lessonsMastery[$progress->lesson_id]['failed']++;
                    $gamesMastery[$test->game_id]['failed']++;
                }
            } else {
                $unitsMastery[$progress->unit_id]['failed']++;
                $lessonsMastery[$progress->lesson_id]['failed']++;
                $gamesMastery[$test->game_id]['failed']++;
            }

            $unitsMastery[$progress->unit_id]['total_score'] += $progress->score;
            $lessonsMastery[$progress->lesson_id]['total_score'] += $progress->score;
            $gamesMastery[$test->game_id]['total_score'] += $progress->score;
        }

        foreach ($unitsMastery as &$unit) {
            $unit['mastery_percentage'] = $unit['total_attempts'] > 0 ? ($unit['total_score'] / $unit['total_attempts']) : 0;
        }

        foreach ($lessonsMastery as &$lesson) {
            $lesson['mastery_percentage'] = $lesson['total_attempts'] > 0 ? ($lesson['total_score'] / $lesson['total_attempts']) : 0;
        }

        foreach ($gamesMastery as &$game) {
            $game['mastery_percentage'] = $game['total_attempts'] > 0 ? ($game['total_score'] / $game['total_attempts']) : 0;
        }

        foreach ($skillsMastery as &$skill) {
            $skill['mastery_percentage'] = $skill['total_attempts'] > 0 ? ($skill['total_score'] / $skill['total_attempts']) : 0;
        }

        if ($request->has('filter_type')) {
            switch ($request->filter_type) {
                case 'skill':
                    $response = array_values($skillsMastery);
                    break;
                case 'unit':
                    $response = array_values($unitsMastery);
                    break;
                case 'lesson':
                    $response = array_values($lessonsMastery);
                    break;
                case 'game':
                    $response = array_values($gamesMastery);
                    break;
                default:
                    $response = [
                        'skills' => array_values($skillsMastery),
                        'units' => array_values($unitsMastery),
                        'lessons' => array_values($lessonsMastery),
                        'games' => array_values($gamesMastery),
                    ];
                    break;
            }
        } else {
            $response = [
                'skills' => array_values($skillsMastery),
                'units' => array_values($unitsMastery),
                'lessons' => array_values($lessonsMastery),
                'games' => array_values($gamesMastery),
            ];
        }

        return response()->json($response);
    }







    public function numOfTrialsReport(Request $request)
    {
        $studentId = $request->input('student_id');
        $programId = $request->input('program_id');
        $filterType = $request->input('filter_type');
        $filterValue = $request->input($filterType . '_id');

        $query = StudentProgress::where('student_id', $studentId)
            ->where('program_id', $programId)
            ->where('is_done', 1);

        if ($filterType && $filterValue) {
            if ($filterType == 'game') {
                $query->whereHas('test', function ($q) use ($filterValue) {
                    $q->where('game_id', $filterValue);
                });
            } elseif ($filterType == 'skill') {
                $query->whereHas('test.game.gameTypes.skills', function ($q) use ($filterValue) {
                    $q->where('skill_id', $filterValue);
                });
            } else {
                $query->where($filterType . '_id', $filterValue);
            }
        }

        if ($request->filled(['from_date', 'to_date']) && $request->from_date != NULL && $request->to_date != NULL) {
            $from_date = Carbon::parse($request->from_date)->startOfDay();
            $to_date = Carbon::parse($request->to_date)->endOfDay();
            $query->whereBetween('created_at', [$from_date, $to_date]);
        }

        $studentProgress = $query->get();
        $response = [];

        foreach ($studentProgress as $progress) {
            $test = Test::find($progress->test_id);
            if (!$test) {
                continue;
            }

            $response[] = [
                'test_name' => $test->name,
                'completion_date' => $progress->created_at->format('Y-m-d'),
                'num_trials' => $progress->mistake_count + 1,
                'score' => $progress->score,
            ];
        }

        return response()->json($response);
    }

    public function skillReport(Request $request)
    {
        $query = StudentProgress::with(['tests', 'tests.game', 'tests.game.gameTypes.skills'])
            ->where('student_id', $request->student_id)
            ->where('program_id', $request->program_id)
            ->where('is_done', 1);

        if ($request->has('skill_id')) {
            $query->whereHas('tests.game.gameTypes.skills', function ($q) use ($request) {
                $q->where('skill_id', $request->skill_id);
            });
        }

        if ($request->filled(['from_date', 'to_date']) && $request->from_date != null && $request->to_date != null) {
            $fromDate = Carbon::parse($request->from_date)->startOfDay();
            $toDate = Carbon::parse($request->to_date)->endOfDay();
            $query->whereBetween('created_at', [$fromDate, $toDate]);
        }

        $studentProgress = $query->get();
        $skillsData = [];

        foreach ($studentProgress as $progress) {
            $test = $progress->test;

            if ($test && $test->game) {
                $game = $test->game;

                if ($game->gameTypes) {
                    foreach ($game->gameTypes->skills->unique('skill') as $gameSkill) {
                        if (!$gameSkill->skill) continue;

                        $skill = $gameSkill->skill;
                        $skillName = $skill->skill;
                        $date = $progress->created_at->format('Y-m-d');

                        $currentLevel = 'Introduced';
                        if ($progress->score >= 80) {
                            $currentLevel = 'Mastered';
                        } elseif ($progress->score >= 60) {
                            $currentLevel = 'Practiced';
                        }

                        if (!isset($skillsData[$skillName])) {
                            $skillsData[$skillName] = [
                                'skill_name' => $skillName,
                                'total_score' => 0,
                                'count' => 0,
                                'average_score' => 0,
                                'current_level' => $currentLevel,
                                'date' => $date,
                            ];
                        }

                        $skillsData[$skillName]['count']++;
                        $skillsData[$skillName]['total_score'] += $progress->score;
                        $skillsData[$skillName]['average_score'] = $skillsData[$skillName]['total_score'] / $skillsData[$skillName]['count'];
                        if ($skillsData[$skillName]['average_score'] >= 80) {
                            $skillsData[$skillName]['current_level'] = 'Mastered';
                        } elseif ($skillsData[$skillName]['average_score'] >= 60) {
                            $skillsData[$skillName]['current_level'] = 'Practiced';
                        } else {
                            $skillsData[$skillName]['current_level'] = 'Introduced';
                        }
                    }
                }
            }
        }

        $data = [
            'student_latest' => 'Some latest progress data', // Example data
            'skillsData' => $skillsData,
        ];

        return response()->json(['data' => $data]);
    }


    /////////////////Start Class functions/////////////////////

    public function selectGroup()
    {
        $groups = Group::all();
        return view('dashboard.reports.class.select_group', compact('groups'));
    }

    // public function classCompletionReportWeb(Request $request)
    // {

    //     $groupId = $request->group_id;

    //     // Retrieve all students in the group
    //     $students = GroupStudent::where('group_id', $groupId)->pluck('student_id');
    //     // dd($students);
    //     if ($students->isEmpty()) {
    //         return view('errors.404', ['message' => 'No student progress found.']);
    //     }
    //     // Initialize the query builder for student progress
    //     $progressQuery = StudentTest::with('tests')
    //         ->whereIn('student_id', $students);

    //     if ($progressQuery->get()->isEmpty())
    //         return view('errors.404', ['message' => 'No student progress found.']);

    //     if ($request->filled('future') && $request->future != NULL) {
    //         if ($request->future == 1) {
    //             // No additional conditions needed
    //         } elseif ($request->future == 0) {
    //             $progressQuery->where('start_date', '<=', date('Y-m-d', strtotime(now())));
    //         }
    //     } else {
    //         $progressQuery->where('start_date', '<=', date('Y-m-d', strtotime(now())));
    //     }

    //     // Filter by from and to date if provided
    //     if ($request->filled(['from_date', 'to_date']) && $request->from_date != NULL && $request->to_date != NULL) {
    //         $fromDate = Carbon::parse($request->from_date)->startOfDay();
    //         $toDate = Carbon::parse($request->to_date)->endOfDay();
    //         $progressQuery->whereBetween('due_date', [$fromDate, $toDate]);
    //     }

    //     // Filter by program ID if provided
    //     if ($request->filled('program_id') && $request->program_id != NULL) {
    //         $progressQuery->where('program_id', $request->program_id);
    //     }

    //     // Execute the query
    //     $allTests = $progressQuery->orderBy('due_date', 'DESC')->get();
    //     $totalAllTests = $allTests->count();
    //     $finishedCount = $allTests->where('status', 1)->count();
    //     $overdueCount = $allTests->where('due_date', '<', \Carbon\Carbon::now()->format('Y-m-d'))
    //         ->where('status', '!=', 1)
    //         ->count();
    //     $pendingCount = $totalAllTests - $finishedCount - $overdueCount;

    //     // Calculate percentages as integers
    //     $finishedPercentage = $totalAllTests > 0 ? round(($finishedCount / $totalAllTests) * 100, 2) : 0;
    //     $overduePercentage = $totalAllTests > 0 ? round(($overdueCount / $totalAllTests) * 100, 2) : 0;
    //     $pendingPercentage = $totalAllTests > 0 ? round(($pendingCount / $totalAllTests) * 100, 2) : 0;

    //     // Filter by status if provided
    //     if ($request->filled('status') && $request->status != NULL) {
    //         $now = \Carbon\Carbon::now();
    //         $status = $request->status;
    //         switch ($status) {
    //             case 'Completed':
    //                 $progressQuery->where('status', '1');
    //                 break;
    //             case 'Overdue':
    //                 $progressQuery->where('due_date', '<', $now->format('Y-m-d'))->where('status', '!=', 1);
    //                 break;
    //             case 'Pending':
    //                 $progressQuery->where('status', '0')->where('due_date', '>=', $now->format('Y-m-d'));
    //                 break;
    //             default:
    //                 // Invalid status provided
    //                 break;
    //         }
    //     }

    //     // Filter by assignment types if provided
    //     if ($request->filled('types') && $request->types != NULL) {
    //         $assignmentTypes = $request->types;
    //         $progressQuery->whereHas('tests', function ($q) use ($assignmentTypes) {
    //             $q->join('test_types', 'tests.type', '=', 'test_types.id')
    //                 ->whereIn('test_types.id', $assignmentTypes);
    //         });
    //     }

    //     // Execute the query
    //     $tests = $progressQuery->orderBy('due_date', 'DESC')->get();

    //     // Prepare response data
    //     $test_types = TestTypes::all();

    //     $data['counts'] = [
    //         'completed' => $finishedCount,
    //         'overdue' => $overdueCount,
    //         'pending' => $pendingCount,
    //     ];
    //     $data['assignments_percentages'] = [
    //         'completed' => ceil($finishedPercentage),
    //         'overdue' => floor($overduePercentage),
    //         'pending' => ceil($pendingPercentage),
    //     ];
    //     $data['tests'] = $tests;
    //     $data['test_types'] = $test_types;

    //     $user_id = auth()->user()->id;
    //     $courses = DB::table('user_courses')
    //         ->join('programs', 'user_courses.program_id', '=', 'programs.id')
    //         ->join('courses', 'programs.course_id', '=', 'courses.id')
    //         ->where('user_courses.user_id', $user_id)
    //         ->select('programs.id as program_id', 'courses.name as course_name')
    //         ->get();

    //     // Add the "all programs" entry
    //     $allProgramsEntry = (object) [
    //         'program_id' => null,
    //         'course_name' => 'All Programs'
    //     ];
    //     $courses->prepend($allProgramsEntry);

    //     $data['courses'] = $courses;

    //     // Return view with data
    //     return view('dashboard.reports.class.class_completion_report', $data);
    // }

    public function classCompletionReportWeb(Request $request)
    {
        $groups = Group::all();
        $programs = Program::all();
        $assignmentTypes = TestTypes::all();

        $data = [
            'groups' => $groups,
            'programs' => $programs,
            'assignmentTypes' => $assignmentTypes,
        ];

        if ($request->has('group_id')) {
            $groupId = $request->group_id;

            // Retrieve all students in the group
            $students = GroupStudent::where('group_id', $groupId)->pluck('student_id');

            if ($students->isEmpty()) {
                return view('errors.404', ['message' => 'No student progress found.']);
            }

            // Initialize the query builder for student progress
            $progressQuery = StudentTest::with('tests')->whereIn('student_id', $students);

            if ($progressQuery->get()->isEmpty()) {
                return view('errors.404', ['message' => 'No student progress found.']);
            }

            if ($request->filled('future') && $request->future != NULL) {
                if ($request->future == 1) {
                    // No additional conditions needed
                } elseif ($request->future == 0) {
                    $progressQuery->where('start_date', '<=', date('Y-m-d', strtotime(now())));
                }
            } else {
                $progressQuery->where('start_date', '<=', date('Y-m-d', strtotime(now())));
            }

            // Filter by from and to date if provided
            if ($request->filled(['from_date', 'to_date']) && $request->from_date != NULL && $request->to_date != NULL) {
                $fromDate = Carbon::parse($request->from_date)->startOfDay();
                $toDate = Carbon::parse($request->to_date)->endOfDay();
                $progressQuery->whereBetween('due_date', [$fromDate, $toDate]);
            }

            // Filter by program ID if provided
            if ($request->filled('program_id') && $request->program_id != NULL) {
                $progressQuery->where('program_id', $request->program_id);
            }

            // Execute the query
            $allTests = $progressQuery->orderBy('due_date', 'DESC')->get();
            $totalAllTests = $allTests->count();
            $finishedCount = $allTests->where('status', 1)->count();
            $overdueCount = $allTests->where('due_date', '<', \Carbon\Carbon::now()->format('Y-m-d'))
                ->where('status', '!=', 1)
                ->count();
            $pendingCount = $totalAllTests - $finishedCount - $overdueCount;

            // Calculate percentages as integers
            $finishedPercentage = $totalAllTests > 0 ? round(($finishedCount / $totalAllTests) * 100, 2) : 0;
            $overduePercentage = $totalAllTests > 0 ? round(($overdueCount / $totalAllTests) * 100, 2) : 0;
            $pendingPercentage = $totalAllTests > 0 ? round(($pendingCount / $totalAllTests) * 100, 2) : 0;

            // Filter by status if provided
            if ($request->filled('status') && $request->status != NULL) {
                $now = \Carbon\Carbon::now();
                $status = $request->status;
                switch ($status) {
                    case 'Completed':
                        $progressQuery->where('status', '1');
                        break;
                    case 'Overdue':
                        $progressQuery->where('due_date', '<', $now->format('Y-m-d'))->where('status', '!=', 1);
                        break;
                    case 'Pending':
                        $progressQuery->where('status', '0')->where('due_date', '>=', $now->format('Y-m-d'));
                        break;
                    default:
                        // Invalid status provided
                        break;
                }
            }

            // Filter by assignment types if provided
            if ($request->filled('types') && $request->types != NULL) {
                $assignmentTypes = $request->types;
                $progressQuery->whereHas('tests', function ($q) use ($assignmentTypes) {
                    $q->join('test_types', 'tests.type', '=', 'test_types.id')
                        ->whereIn('test_types.id', $assignmentTypes);
                });
            }

            // Execute the query
            $tests = $progressQuery->orderBy('due_date', 'DESC')->get();

            // Prepare response data
            $data['counts'] = [
                'completed' => $finishedCount,
                'overdue' => $overdueCount,
                'pending' => $pendingCount,
            ];
            $data['assignments_percentages'] = [
                'completed' => ceil($finishedPercentage),
                'overdue' => floor($overduePercentage),
                'pending' => ceil($pendingPercentage),
            ];
            $data['tests'] = $tests;
        }

        return view('dashboard.reports.class.class_completion_report', $data);
    }
    public function classMasteryReportWeb(Request $request)
    {
        // Retrieve necessary data for filters
        $groups = Group::all();
        $programs = Program::all();

        $data = [
            'groups' => $groups,
            'programs' => $programs,
        ];

        if ($request->has('group_id')) {
            // Retrieve all students in the specified group
            $students = GroupStudent::where('group_id', $request->group_id)->pluck('student_id');

            if ($students->isEmpty()) {
                return view('errors.404', ['message' => 'No student progress found.']);
            }

            // Initialize query builder for student progress
            $query = StudentProgress::whereIn('student_id', $students)
                ->where('program_id', $request->program_id);

            if ($query->get()->isEmpty()) {
                return view('errors.404', ['message' => 'No student progress found.']);
            }

            // Apply filters if provided
            if ($request->has('unit_id')) {
                $query->where('unit_id', $request->unit_id);
            }
            if ($request->has('lesson_id')) {
                $query->where('lesson_id', $request->lesson_id);
            }
            if ($request->has('game_id')) {
                $query->whereHas('test', function ($q) use ($request) {
                    $q->where('game_id', $request->game_id);
                });
            }
            if ($request->has('skill_id')) {
                $query->whereHas('test.game.gameTypes.skills', function ($q) use ($request) {
                    $q->where('skill_id', $request->skill_id);
                });
            }

            if ($request->filled(['from_date', 'to_date']) && $request->from_date != NULL && $request->to_date != NULL) {
                $fromDate = Carbon::parse($request->from_date)->startOfDay();
                $toDate = Carbon::parse($request->to_date)->endOfDay();
                $query->whereBetween('created_at', [$fromDate, $toDate]);
            }

            $student_progress = $query->get();

            // Initialize arrays to hold data for grouping
            $unitsMastery = [];
            $lessonsMastery = [];
            $gamesMastery = [];
            $skillsMastery = [];

            if ($student_progress->isEmpty()) {
                return view('errors.404', ['message' => 'No student progress found.']);
            }

            // Process each progress record
            foreach ($student_progress as $progress) {
                // Retrieve the test and its related game, game type, and skills
                $test = Test::with(['game.gameTypes.skills.skill'])->where('lesson_id', $progress->lesson_id)->find($progress->test_id);

                // Check if the test and its relationships are properly loaded
                if (!$test || !$test->game || !$test->game->gameTypes) {
                    continue; // Skip to the next progress record if any of these are null
                }

                // Get the game type (since each game has one game type)
                $gameType = $test->game->gameTypes;

                // Group by unit
                if (!isset($unitsMastery[$progress->unit_id])) {
                    $unitsMastery[$progress->unit_id] = [
                        'unit_id' => $progress->unit_id,
                        'name' => Unit::find($progress->unit_id)->name,
                        'failed' => 0,
                        'introduced' => 0,
                        'practiced' => 0,
                        'mastered' => 0,
                        'total_attempts' => 0,
                        'total_score' => 0,
                        'mastery_percentage' => 0,
                        'lessons' => [],
                    ];
                }

                // Group by lesson
                if (!isset($lessonsMastery[$progress->lesson_id])) {
                    $lessonsMastery[$progress->lesson_id] = [
                        'lesson_id' => $progress->lesson_id,
                        'name' => Unit::find(Lesson::find($progress->lesson_id)->unit_id)->name . " | " . Lesson::find($progress->lesson_id)->name,
                        'failed' => 0,
                        'introduced' => 0,
                        'practiced' => 0,
                        'mastered' => 0,
                        'total_attempts' => 0,
                        'total_score' => 0,
                        'mastery_percentage' => 0,
                        'games' => [],
                    ];
                }

                // Group by game type
                if (!isset($gameTypesMastery[$gameType->id])) {
                    $gameTypesMastery[$gameType->id] = [
                        'game_type_id' => $gameType->id,
                        'name' => GameType::find($gameType->id)->name,
                        'failed' => 0,
                        'introduced' => 0,
                        'practiced' => 0,
                        'mastered' => 0,
                        'total_attempts' => 0,
                        'count' => 0,
                        'total_score' => 0,
                        'games' => [],
                    ];
                }

                // Group by game within the game type
                if (!isset($gameTypesMastery[$gameType->id]['games'][$test->game_id])) {
                    $gameTypesMastery[$gameType->id]['games'][$test->game_id] = [
                        'game_id' => $test->game_id,
                        'name' => Game::find($test->game_id)->name,
                        'failed' => 0,
                        'introduced' => 0,
                        'practiced' => 0,
                        'mastered' => 0,
                        'total_attempts' => 0,
                        'count' => 0,
                        'total_score' => 0,
                    ];
                }

                // Group by skill
                if ($gameType && $gameType->skills) {
                    foreach ($gameType->skills->where('lesson_id', $progress->lesson_id)->unique('skill') as $gameSkill) {
                        $skill = $gameSkill->skill;

                        if (!isset($skillsMastery[$skill->id])) {
                            $skillsMastery[$skill->id] = [
                                'skill_id' => $skill->id,
                                'name' => $skill->skill,
                                'failed' => 0,
                                'introduced' => 0,
                                'practiced' => 0,
                                'mastered' => 0,
                                'total_attempts' => 0,
                                'total_score' => 0,
                                'mastery_percentage' => 0,
                            ];
                        }

                        $skillsMastery[$skill->id]['total_attempts']++;
                        if ($progress->is_done) {
                            if ($progress->score >= 80) {
                                $skillsMastery[$skill->id]['mastered']++;
                            } elseif ($progress->score >= 60) {
                                $skillsMastery[$skill->id]['practiced']++;
                            } elseif ($progress->score >= 30) {
                                $skillsMastery[$skill->id]['introduced']++;
                            } else {
                                $skillsMastery[$skill->id]['failed']++;
                            }
                        } else {
                            $skillsMastery[$skill->id]['failed']++;
                        }
                        $skillsMastery[$skill->id]['total_score'] += $progress->score;
                    }
                }

                // Update totals for units, lessons, and game types
                $unitsMastery[$progress->unit_id]['total_attempts']++;
                $lessonsMastery[$progress->lesson_id]['total_attempts']++;
                $gameTypesMastery[$gameType->id]['total_attempts']++;
                $gameTypesMastery[$gameType->id]['games'][$test->game_id]['total_attempts']++;

                if ($progress->is_done) {
                    if ($progress->score >= 80) {
                        $unitsMastery[$progress->unit_id]['mastered']++;
                        $lessonsMastery[$progress->lesson_id]['mastered']++;
                        $gameTypesMastery[$gameType->id]['mastered']++;
                        $gameTypesMastery[$gameType->id]['games'][$test->game_id]['mastered']++;
                    } elseif ($progress->score >= 60) {
                        $unitsMastery[$progress->unit_id]['practiced']++;
                        $lessonsMastery[$progress->lesson_id]['practiced']++;
                        $gameTypesMastery[$gameType->id]['practiced']++;
                        $gameTypesMastery[$gameType->id]['games'][$test->game_id]['practiced']++;
                    } elseif ($progress->score >= 30) {
                        $unitsMastery[$progress->unit_id]['introduced']++;
                        $lessonsMastery[$progress->lesson_id]['introduced']++;
                        $gameTypesMastery[$gameType->id]['introduced']++;
                        $gameTypesMastery[$gameType->id]['games'][$test->game_id]['introduced']++;
                    } else {
                        $unitsMastery[$progress->unit_id]['failed']++;
                        $lessonsMastery[$progress->lesson_id]['failed']++;
                        $gameTypesMastery[$gameType->id]['failed']++;
                        $gameTypesMastery[$gameType->id]['games'][$test->game_id]['failed']++;
                    }
                } else {
                    $unitsMastery[$progress->unit_id]['failed']++;
                    $lessonsMastery[$progress->lesson_id]['failed']++;
                    $gameTypesMastery[$gameType->id]['failed']++;
                    $gameTypesMastery[$gameType->id]['games'][$test->game_id]['failed']++;
                }

                $unitsMastery[$progress->unit_id]['total_score'] += $progress->score;
                $lessonsMastery[$progress->lesson_id]['total_score'] += $progress->score;
                $gameTypesMastery[$gameType->id]['total_score'] += $progress->score;
                $gameTypesMastery[$gameType->id]['games'][$test->game_id]['total_score'] += $progress->score;
                $gameTypesMastery[$gameType->id]['games'][$test->game_id]['count']++;

                // Group lessons under units
                if (!isset($unitsMastery[$progress->unit_id]['lessons'][$progress->lesson_id])) {
                    $unitsMastery[$progress->unit_id]['lessons'][$progress->lesson_id] = [
                        'lesson_id' => $progress->lesson_id,
                        'failed' => 0,
                        'introduced' => 0,
                        'practiced' => 0,
                        'mastered' => 0,
                        'total_attempts' => 0,
                        'total_score' => 0,
                        'mastery_percentage' => 0,
                    ];
                }

                // Aggregate lesson data under the unit
                $unitsMastery[$progress->unit_id]['lessons'][$progress->lesson_id]['failed'] += $lessonsMastery[$progress->lesson_id]['failed'];
                $unitsMastery[$progress->unit_id]['lessons'][$progress->lesson_id]['introduced'] += $lessonsMastery[$progress->lesson_id]['introduced'];
                $unitsMastery[$progress->unit_id]['lessons'][$progress->lesson_id]['practiced'] += $lessonsMastery[$progress->lesson_id]['practiced'];
                $unitsMastery[$progress->unit_id]['lessons'][$progress->lesson_id]['mastered'] += $lessonsMastery[$progress->lesson_id]['mastered'];
                $unitsMastery[$progress->unit_id]['lessons'][$progress->lesson_id]['total_attempts'] += $lessonsMastery[$progress->lesson_id]['total_attempts'];
                $unitsMastery[$progress->unit_id]['lessons'][$progress->lesson_id]['total_score'] += $lessonsMastery[$progress->lesson_id]['total_score'];
            }

            // Ensure all lessons are included in units
            foreach ($unitsMastery as &$unit) {
                foreach ($lessonsMastery as $lessonId => $lessonData) {
                    if (!isset($unit['lessons'][$lessonId])) {
                        $unit['lessons'][$lessonId] = [
                            'lesson_id' => $lessonId,
                            'failed' => 0,
                            'introduced' => 0,
                            'practiced' => 0,
                            'mastered' => 0,
                            'total_attempts' => 0,
                            'total_score' => 0,
                            'mastery_percentage' => 0,
                        ];
                    }
                }
            }

            // Calculate mastery percentages for units, lessons, games, and game types
            foreach ($unitsMastery as &$unitData) {
                $unitData['mastery_percentage'] = $unitData['total_attempts'] > 0 ? ($unitData['total_score'] / $unitData['total_attempts']) : 0;

                foreach ($unitData['lessons'] as &$lessonData) {
                    $lessonData['mastery_percentage'] = $lessonData['total_attempts'] > 0 ? ($lessonData['total_score'] / $lessonData['total_attempts']) : 0;
                }

                $unitData['lessons'] = array_values($unitData['lessons']); // Convert lessons to array
            }

            foreach ($lessonsMastery as &$lessonData) {
                $lessonData['mastery_percentage'] = $lessonData['total_attempts'] > 0 ? ($lessonData['total_score'] / $lessonData['total_attempts']) : 0;
            }

            foreach ($gameTypesMastery as &$gameTypeData) {
                foreach ($gameTypeData['games'] as &$gameData) {
                    $gameData['mastery_percentage'] = $gameData['total_attempts'] > 0 ? ($gameData['total_score'] / $gameData['total_attempts']) : 0;
                }
                $gameTypeData['games'] = array_values($gameTypeData['games']); // Convert games to array

                $gameTypeData['mastery_percentage'] = $gameTypeData['total_attempts'] > 0 ? ($gameTypeData['total_score'] / $gameTypeData['total_attempts']) : 0;
            }

            // Calculate skill mastery level based on mastered, practiced, introduced, and failed counts
            foreach ($skillsMastery as &$skillData) {
                if ($skillData['mastered'] > $skillData['practiced'] && $skillData['mastered'] > $skillData['introduced'] && $skillData['mastered'] > $skillData['failed']) {
                    $skillData['current_level'] = 'mastered';
                    $skillData['mastery_percentage'] = $skillData['total_score'] / $skillData['total_attempts'] > 100 ? 100 : $skillData['total_score'] / $skillData['total_attempts'];
                } elseif ($skillData['practiced'] > $skillData['introduced'] && $skillData['practiced'] > $skillData['failed']) {
                    $skillData['current_level'] = 'practiced';
                    $skillData['mastery_percentage'] = $skillData['total_score'] / $skillData['total_attempts'] > 100 ? 100 : $skillData['total_score'] / $skillData['total_attempts'];
                } elseif ($skillData['introduced'] > $skillData['failed']) {
                    $skillData['current_level'] = 'introduced';
                    $skillData['mastery_percentage'] = $skillData['total_score'] / $skillData['total_attempts'] > 100 ? 100 : $skillData['total_score'] / $skillData['total_attempts'];
                } else {
                    $skillData['current_level'] = 'failed';
                    $skillData['mastery_percentage'] = $skillData['total_score'] / $skillData['total_attempts'] > 100 ? 100 : $skillData['total_score'] / $skillData['total_attempts'];
                }
            }

            // Prepare the response data
            if ($request->has('filter')) {
                switch ($request->filter) {
                    case 'Skill':
                        $data['skills'] = array_values($skillsMastery);
                        break;
                    case 'Unit':
                        $data['units'] = array_values($unitsMastery);
                        break;
                    case 'Lesson':
                        $data['lessons'] = array_values($lessonsMastery);
                        break;
                    case 'Game':
                        $data['games'] = array_values($gameTypesMastery);
                        break;
                    default:
                        $data['skills'] = array_values($skillsMastery);
                        $data['units'] = array_values($unitsMastery);
                        $data['lessons'] = array_values($lessonsMastery);
                        $data['games'] = array_values($gameTypesMastery);
                        break;
                }
            } else {
                $data['skills'] = array_values($skillsMastery);
                $data['units'] = array_values($unitsMastery);
                $data['lessons'] = array_values($lessonsMastery);
                $data['games'] = array_values($gameTypesMastery);
            }
        }

        // Return view with data
        return view('dashboard.reports.class.class_mastery_report', $data);
    }
    public function classNumOfTrialsReportWeb(Request $request)
    {
        // Retrieve groups and programs for the filters
        $groups = Group::all();
        $programs = Program::all();

        // Get the student IDs for the given group ID
        $students = GroupStudent::where('group_id', $request->group_id)->pluck('student_id');

        // Check if students exist for the given group
        // if ($students->isEmpty()) {
        //     return view('errors.404', ['message' => 'No students found for the selected group.']);
        // }

        // Initialize query builder with student IDs and program ID
        $progressQuery = StudentProgress::whereIn('student_id', $students)
            ->where('program_id', $request->program_id);

        // Check if student progress exists for the given program
        // if ($progressQuery->get()->isEmpty()) {
        //     return view('errors.404', ['message' => 'No student progress found.']);
        // }

        if ($request->filled(['from_date', 'to_date']) && $request->from_date != NULL && $request->to_date != NULL) {
            $from_date = Carbon::parse($request->from_date)->startOfDay();
            $to_date = Carbon::parse($request->to_date)->endOfDay();
            $progressQuery->whereBetween('created_at', [$from_date, $to_date]);
        }

        // Filter by month of created_at date if provided
        if ($request->filled('month')) {
            $month = $request->month;
            $progressQuery->whereMonth('created_at', Carbon::parse($month)->month);
        }

        // Filter by test_types if provided
        if ($request->filled('type')) {
            $type = $request->type;
            $progressQuery->join('tests', 'student_progress.test_id', '=', 'tests.id')
                ->where('tests.type', $type);
        }

        // Filter by stars if provided
        if ($request->filled('stars')) {
            $stars = (array) $request->stars;
            if ($request->stars == 2) {
                $progressQuery->whereIn('mistake_count', range(2, 1000));
            } else {
                $progressQuery->whereIn('mistake_count', $stars);
            }
        }

        // Get the progress data
        $progress = $progressQuery->orderBy('created_at', 'ASC')
            ->select('student_progress.*')
            ->get();

        // Check if progress data is empty
        // if ($progress->isEmpty()) {
        //     return view('errors.404', ['message' => 'No student progress found after applying filters.']);
        // }

        // Initialize arrays to hold the data
        $monthlyScores = [];
        $starCounts = [];

        foreach ($progress as $course) {
            $createdDate = Carbon::parse($course->created_at);
            $monthYear = $createdDate->format('Y-m');

            // Calculate the number of trials
            $numTrials = $course->mistake_count + 1;

            // Calculate the score for each test
            $testScore = [
                'name' => $course->test_name,
                'test_id' => $course->test_id,
                'score' => $course->score,
                'star' => $course->stars,  // Include star in the testScore for filtering
                'num_trials' => $numTrials
            ];

            // Add the test score to the respective month
            if (!isset($monthlyScores[$monthYear])) {
                $monthlyScores[$monthYear] = [
                    'month' => $createdDate->format('M'),
                    'total_score' => 0,
                    'star' => $course->stars,
                    'tests' => [],
                ];
            }

            $monthlyScores[$monthYear]['tests'][] = $testScore;
            $monthlyScores[$monthYear]['total_score'] += $course->score;

            // Count stars
            $star = $course->stars;
            if (isset($starCounts[$star])) {
                $starCounts[$star]++;
            } else {
                $starCounts[$star] = 1;
            }
        }

        $totalDisplayedStars = array_sum($starCounts);
        $oneStarDisplayedCount = isset($starCounts[1]) ? $starCounts[1] : 0;
        $twoStarDisplayedCount = isset($starCounts[2]) ? $starCounts[2] : 0;
        $threeStarDisplayedCount = isset($starCounts[3]) ? $starCounts[3] : 0;

        // Filter progress by stars if provided
        if ($request->filled('stars')) {
            $stars = (array) $request->stars;
            $data['tprogress'] = array_filter($monthlyScores, function ($monthlyScore) use ($stars) {
                foreach ($monthlyScore['tests'] as $test) {
                    if (in_array($test['star'], $stars)) {
                        return true;
                    }
                }
                return false;
            });
        } else {
            $data['tprogress'] = array_values($monthlyScores);
        }

        $oneStarDisplayedPercentage = $totalDisplayedStars > 0 ? round(($oneStarDisplayedCount / $totalDisplayedStars) * 100, 2) : 0;
        $twoStarDisplayedPercentage = $totalDisplayedStars > 0 ? round(($twoStarDisplayedCount / $totalDisplayedStars) * 100, 2) : 0;
        $threeStarDisplayedPercentage = $totalDisplayedStars > 0 ? round(($threeStarDisplayedCount / $totalDisplayedStars) * 100, 2) : 0;

        // Prepare response data
        $data['progress'] = $progress;

        if ($request->filled('stars')) {
            $data['counts'] = StudentProgress::where('stars', $request->stars)->count();
        } else {
            $data['counts'] = StudentProgress::whereIn('student_id', $students)
                ->where('program_id', $request->program_id)
                ->count();
        }

        $division = StudentProgress::whereIn('student_id', $students)->count();
        if ($division == 0) {
            $division = 1;
        }

        if (!$request->filled('from_date') && !$request->filled('to_date')) {
            $data['reports_percentages'] = [
                'first_trial' => round((StudentProgress::where('mistake_count', 0)->whereIn('student_id', $students)
                    ->where('program_id', $request->program_id)->count() / $division) * 100) ?? 0,
                'second_trial' => round((StudentProgress::where('mistake_count', 1)->whereIn('student_id', $students)
                    ->where('program_id', $request->program_id)->count() / $division) * 100) ?? 0,
                'third_trial' => round((StudentProgress::whereIn('mistake_count', [2, 3, 4, 5, 6, 7, 8, 9, 10, 11])->whereIn('student_id', $students)
                    ->where('program_id', $request->program_id)->count() / $division) * 100) ?? 0,
            ];
        } else {
            $threestars = StudentProgress::where('mistake_count', 0)->whereIn('student_id', $students)->whereBetween('student_progress.created_at', [$from_date, $to_date])
                ->where('program_id', $request->program_id)->count();
            $twostars = StudentProgress::where('mistake_count', 1)->whereIn('student_id', $students)->whereBetween('student_progress.created_at', [$from_date, $to_date])
                ->where('program_id', $request->program_id)->count();
            $onestar = StudentProgress::whereIn('mistake_count', [2, 3, 4, 5, 6, 7, 8, 9, 10, 11])->whereIn('student_id', $students)->whereBetween('student_progress.created_at', [$from_date, $to_date])
                ->where('program_id', $request->program_id)->count();

            $division = StudentProgress::whereIn('student_id', $students)
                ->whereBetween('student_progress.created_at', [$from_date, $to_date])->count();

            if ($division == 0) {
                $division = 1;
            }

            $data['reports_percentages'] = [
                'first_trial' => round(($threestars / $division) * 100, 1),
                'second_trial' => round(($twostars / $division) * 100, 1),
                'third_trial' => round(($onestar / $division) * 100, 1),
            ];
        }

        $data['groups'] = $groups;
        $data['programs'] = $programs;
        $data['oneStarDisplayedPercentage'] = $oneStarDisplayedPercentage;
        $data['twoStarDisplayedPercentage'] = $twoStarDisplayedPercentage;
        $data['threeStarDisplayedPercentage'] = $threeStarDisplayedPercentage;

        return view('dashboard.reports.class.class_num_of_trials_report', $data);
    }
}
