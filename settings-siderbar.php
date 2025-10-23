<ul>

    <li class="submenu">
        <a href="javascript:void(0);" class="subdrop"><i data-feather="airplay"></i><span>System
                Settings</span><span class="menu-arrow"></span></a>
        <ul>
            <li><a href="<?= getenv("BASE_URL") . "system-settings" ?>">System
                    Settings</a></li>
            <li><a href="<?= getenv("BASE_URL") . "email-settings" ?>">Email</a>
            </li>
            <li><a href="<?= getenv("BASE_URL") . "email-template-settings" ?>">Email Template</a>
            </li>
            <li><a href="<?= getenv("BASE_URL") . "company-settings" ?>">Company Settings</a>
            </li>
            <li>
                <a href="<?= getenv("BASE_URL") . "localization-settings" ?>">Localization</a>
            </li>

        </ul>
        <a href="javascript:void(0);"><i data-feather="archive"></i><span>App
                Settings</span><span class="menu-arrow"></span></a>
        <ul>
            <li><a href="<?= getenv("BASE_URL") . "invoice-settings" ?>">Invoice</a></li>
        </ul>
    </li>

    <li class="submenu">
        <a href="javascript:void(0);"><i data-feather="credit-card"></i><span>Financial Settings</span><span
                class="menu-arrow"></span></a>
        <ul>
            <li>
                <a href="<?= getenv("BASE_URL") . "currency-settings" ?>">Currencies</a>
            </li>
        </ul>
    </li>
</ul>