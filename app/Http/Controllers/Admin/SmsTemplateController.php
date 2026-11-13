<?php

namespace App\Http\Controllers\Admin;

use App\Constants\SmsTemplateConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\Admin\SmsTemplate;
use App\Providers\Admin\BasicSettingsProvider;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SmsTemplateController extends Controller
{
    /**
     * SMS templates of an account type (user/agent/merchant)
     * @param string $group
     */
    public function index($group = SmsTemplateConst::GROUP_USER)
    {
        if(!isset(SmsTemplateConst::ACCOUNT_GROUPS[$group])) abort(404);

        $page_title = __(SmsTemplateConst::ACCOUNT_GROUPS[$group]);
        // only SMS this account type receives, in catalog order
        $acts = array_keys(array_filter(SmsTemplateConst::TEMPLATES, fn($config) => in_array($group, $config['groups'])));
        $sms_templates = SmsTemplate::with('admin')->where('group', $group)->whereIn('act', $acts)->get()
                            ->sortBy(fn($item) => array_search($item->act, $acts))->values();

        return view('admin.sections.sms-templates.index',compact(
            'page_title',
            'group',
            'sms_templates',
        ));
    }

    /**
     * Show the form for editing SMS template
     * @param int $id
     */
    public function edit($id)
    {
        $page_title = __("Edit SMS Template");
        $sms_template = SmsTemplate::find($id);
        if(!$sms_template) return back()->with(['error' => [__("Target not found!")]]);

        $variables = SmsTemplateConst::variables($sms_template->act);
        $required = SmsTemplateConst::required($sms_template->act);
        return view('admin.sections.sms-templates.edit',compact(
            'page_title',
            'sms_template',
            'variables',
            'required',
        ));
    }

    /**
     * Update SMS template
     * @param \Illuminate\Http\Request $request
     * @param int $id
     */
    public function update(Request $request, $id)
    {
        $sms_template = SmsTemplate::find($id);
        if(!$sms_template) return back()->with(['error' => [__("Target not found!")]]);

        $validator = Validator::make($request->all(),[
            'sms_body'  => 'required|string|max:1000',
        ]);
        $required = SmsTemplateConst::required($sms_template->act);
        $validator->after(function($validator) use ($request, $required) {
            if($required && !str_contains((string) $request->sms_body, '{{'.$required.'}}')) {
                $validator->errors()->add('sms_body', __("Body must contain :placeholder placeholder", ['placeholder' => '{{'.$required.'}}']));
            }
        });

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validate();
        $validated['last_edit_by'] = auth()->user()->id;

        try{
            $sms_template->update($validated);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }

        return redirect()->route('admin.sms.template.index', $sms_template->group)->with(['success' => [__("SMS template updated successfully!")]]);
    }

    /**
     * Preview SMS template with sample data
     * @param int $id
     */
    public function preview($id)
    {
        $page_title = __("SMS Template Preview");
        $sms_template = SmsTemplate::find($id);
        if(!$sms_template) return back()->with(['error' => [__("Target not found!")]]);

        // full text as sent: SMS Method message format ("Hi {{name}}, {{message}}") + template
        $message_format = BasicSettingsProvider::get()->sms_api ?: "{{message}}";
        $values = SmsTemplateConst::PREVIEW_VALUES;
        // body shows {{currency}} itself: the real SMS sends a plain amount there, so it isn't shown twice
        if(str_contains((string) $sms_template->sms_body, '{{currency}}')) {
            $values['amount'] = "100.00";
        }
        $sms = str_replace(['{{message}}', '{{name}}'], [$sms_template->render($values), "johndoe"], $message_format);
        $segments = SmsTemplate::segments($sms);
        return view('admin.sections.sms-templates.preview',compact(
            'page_title',
            'sms_template',
            'sms',
            'segments',
        ));
    }

    /**
     * Enable/Disable SMS template
     * @param \Illuminate\Http\Request $request
     */
    public function statusUpdate(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'data_target'   => 'required|numeric',
            'status'        => 'required|integer',
        ]);

        if($validator->stopOnFirstFailure()->fails()) {
            return Response::error($validator->errors());
        }

        $validated = $validator->validate();

        $status = [
            0 => 1,
            1 => 0,
        ];

        $sms_template = SmsTemplate::find($validated['data_target']);
        if(!$sms_template) {
            $error = ['error' => [__("Target not found!")]];
            return Response::error($error,null,404);
        }
        // verification codes / credentials must always be sent
        if(!SmsTemplateConst::canDisable($sms_template->act)) {
            $error = ['error' => [__("This SMS template can't be disabled")]];
            return Response::error($error,null,400);
        }

        try{
            $sms_template->update([
                'sms_status' => $status[$validated['status']] ?? 0,
            ]);
        }catch(Exception $e) {
            $error = ['error' => [__("Something went wrong! Please try again.")]];
            return Response::error($error,null,500);
        }

        $success = ['success' => [__("Status Updated Successfully!")]];
        return Response::success($success);
    }
}
