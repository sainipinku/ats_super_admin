<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\SiteSetting;

class AccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $username;
    public $password;
    public $departmentNames;
    public $designationNames;
    public $email;
    public $emailPassword;
    public $setting; // Add this property

    public function __construct(
        $name,
        $username,
        $password,
        $departmentNames,
        $designationNames,
        $email,
        $emailPassword
    ) {
        $this->name = $name;
        $this->username = $username;
        $this->password = $password;
        $this->departmentNames = $departmentNames;
        $this->designationNames = $designationNames;
        $this->email = $email;
        $this->emailPassword = $emailPassword;
        $this->setting = SiteSetting::first();
    }

    public function build()
    {
        return $this->subject('Your Account Has Been Created')
            ->view('emails.account_creation')
            ->with([
                'name' => $this->name,
                'username' => $this->username,
                'password' => $this->password,
                'departmentNames' => $this->departmentNames,
                'designationNames' => $this->designationNames,
                'email' => $this->email,
                'emailPassword' => $this->emailPassword,
                'setting' => $this->setting,
            ]);
    }
}
