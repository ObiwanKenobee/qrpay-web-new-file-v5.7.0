<?php

namespace App\Http\Controllers\Admin;

use App\Constants\EmailTemplateConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\Admin\Admin;
use App\Models\Admin\EmailTemplate;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailTemplateController extends Controller
{
    /**
     * Display admin email templates and admin email notification settings
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = __("Email Templates");
        $email_templates = EmailTemplate::with('admin')->orderBy('id')->get()->where('group', EmailTemplateConst::GROUP_ADMIN);
        // super admin manages every admin, other admins only their own setting
        $auth_admin = auth()->user();
        $admins = $auth_admin->isSuperAdmin() ? Admin::orderBy('id')->get() : Admin::where('id', $auth_admin->id)->get();
        return view('admin.sections.email-templates.index',compact(
            'page_title',
            'email_templates',
            'admins',
        ));
    }

    /**
     * Display user/agent/merchant email templates
     * @param string $group user|agent|merchant
     */
    public function account($group)
    {
        if(!isset(EmailTemplateConst::ACCOUNT_GROUPS[$group])) abort(404);

        $page_title = __(EmailTemplateConst::ACCOUNT_GROUPS[$group]);
        $email_templates = EmailTemplate::with('admin')->orderBy('id')->get()->where('group', $group);
        return view('admin.sections.email-templates.account',compact(
            'page_title',
            'group',
            'email_templates',
        ));
    }

    /**
     * Show the form for editing email template
     * @param string $slug
     */
    public function edit($slug)
    {
        $page_title = __("Edit Email Template");
        $email_template = EmailTemplate::where('slug',$slug)->first();
        if(!$email_template) return back()->with(['error' => [__("Target not found!")]]);

        // show the current site logo in editor
        $email_template->body = EmailTemplate::syncLogo($email_template->body, $email_template->group);
        $variables = EmailTemplateConst::variables($email_template->slug);
        $required = EmailTemplateConst::required($email_template->slug);
        return view('admin.sections.email-templates.edit',compact(
            'page_title',
            'email_template',
            'variables',
            'required',
        ));
    }

    /**
     * Update email template
     * @param \Illuminate\Http\Request $request
     * @param string $slug
     */
    public function update(Request $request, $slug)
    {
        $email_template = EmailTemplate::where('slug',$slug)->first();
        if(!$email_template) return back()->with(['error' => [__("Target not found!")]]);

        $validator = Validator::make($request->all(),[
            'subject'   => 'required|string|max:255',
            'body'      => 'required|string',
        ]);
        $required = '{{'.EmailTemplateConst::required($email_template->slug).'}}';
        $validator->after(function($validator) use ($request, $required) {
            $body = str_replace(['%7B%7B', '%7D%7D'], ['{{', '}}'], (string) $request->body);
            if(!str_contains($body, $required)) {
                $validator->errors()->add('body', __("Body must contain :placeholder placeholder", ['placeholder' => $required]));
            }
        });

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validate();
        $validated['last_edit_by'] = auth()->user()->id;

        try{
            $email_template->update($validated);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }

        return redirect()->to(self::listUrl($email_template))->with(['success' => [__("Email template updated successfully!")]]);
    }

    /**
     * Preview email template with sample data
     * @param string $slug
     */
    public function preview($slug)
    {
        $page_title = __("Email Template Preview");
        $email_template = EmailTemplate::where('slug',$slug)->first();
        if(!$email_template) return back()->with(['error' => [__("Target not found!")]]);

        // sample rows of the same fields this email sends
        $content = EmailTemplateConst::previewDetails($email_template->slug);
        $email = $email_template->render(array_merge(EmailTemplateConst::PREVIEW_VALUES, [
            'admin_name'    => e(auth()->user()->fullname),
            'user_name'     => e(EmailTemplateConst::PREVIEW_VALUES['user_name']),
            'subject'       => e($email_template->type),
            'title'         => "", // real emails show their own title (e.g. gateway), empty line is removed
            'details'       => EmailTemplate::detailsTable($content),
            'date_time'     => now()->format('Y-m-d h:i:s A'),
        ]));

        return view('admin.sections.email-templates.preview',compact(
            'page_title',
            'email_template',
            'email',
        ));
    }

    /**
     * Update template status or admin email notification status
     * @param \Illuminate\Http\Request $request
     */
    public function statusUpdate(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'data_target'   => 'required|numeric',
            'status'        => 'required|integer',
            'input_name'    => 'required|string|in:status,email_notification',
        ]);

        if($validator->stopOnFirstFailure()->fails()) {
            return Response::error($validator->errors());
        }

        $validated = $validator->validate();

        $status = [
            0 => true,
            1 => false,
        ];

        if($validated['input_name'] == "email_notification") {
            $auth_admin = auth()->user();
            if(!$auth_admin->isSuperAdmin() && $validated['data_target'] != $auth_admin->id) {
                $error = ['error' => [__("You can only change your own email notification")]];
                return Response::error($error,null,403);
            }
            $item = Admin::find($validated['data_target']);
            $not_found = __("Admin not found!");
        }else {
            $item = EmailTemplate::find($validated['data_target']);
            $not_found = __("Target not found!");
            // auth emails (OTP/reset link) must always be sent
            if($item && !EmailTemplateConst::canDisable($item->slug)) {
                $error = ['error' => [__("This email template can't be disabled")]];
                return Response::error($error,null,400);
            }
        }

        if(!$item) {
            $error = ['error' => [$not_found]];
            return Response::error($error,null,404);
        }

        try{
            $item->update([
                $validated['input_name'] => $status[$validated['status']] ?? false,
            ]);
        }catch(Exception $e) {
            $error = ['error' => [__("Something went wrong! Please try again.")]];
            return Response::error($error,null,500);
        }

        $success = ['success' => [__("Status Updated Successfully!")]];
        return Response::success($success);
    }

    /**
     * Template list page the template belongs to
     * @param \App\Models\Admin\EmailTemplate $email_template
     * @return string
     */
    public static function listUrl(EmailTemplate $email_template): string
    {
        return $email_template->group == EmailTemplateConst::GROUP_ADMIN
                ? setRoute('admin.email.template.index')
                : setRoute('admin.email.template.account', $email_template->group);
    }
}
