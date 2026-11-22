<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmDeal;
use App\Models\CrmDealStageHistory;
use App\Models\CrmPipeline;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CrmPipelineController extends Controller
{
    public function index()
    {
        $pipelines = CrmPipeline::orderBy('name')->get();

        $deals = CrmDeal::with(['contact', 'organization', 'assignedTo'])
            ->where('status', 'open')
            ->get()
            ->groupBy('stage');

        // Assuming typical stages exist or defining default
        $stages = ['Lead', 'Meeting Scheduled', 'Proposal Sent', 'Negotiation', 'Won', 'Lost'];

        return Inertia::render('Admin/CRM/PipelineBoard', [
            'pipelines' => $pipelines,
            'deals' => $deals,
            'stages' => $stages,
        ]);
    }

    public function updateDealStage(Request $request, CrmDeal $deal)
    {
        $request->validate([
            'new_stage' => 'required|string',
        ]);

        $oldStage = $deal->stage;
        $newStage = $request->new_stage;

        if ($oldStage !== $newStage) {
            $deal->update([
                'stage' => $newStage,
                'status' => strtolower($newStage) === 'won' ? 'won' : (strtolower($newStage) === 'lost' ? 'lost' : 'open'),
            ]);

            CrmDealStageHistory::create([
                'deal_id' => $deal->id,
                'previous_stage' => $oldStage,
                'new_stage' => $newStage,
                'moved_by_user_id' => auth()->id(),
            ]);
        }

        return redirect()->back()->with('success', 'Deal stage updated successfully');
    }
}
