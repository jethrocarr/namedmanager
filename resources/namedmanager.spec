Summary: A web-based management system for DNS, consisting of a PHP web interface and some PHP CLI components to hook into FreeRadius.
Name: namedmanager
Version: 1.0.0
Release: 1.beta.2%{dist}
License: AGPLv3
URL: http://www.amberdms.com/namedmanager
Group: Applications/Internet
Source0: namedmanager-%{version}.tar.bz2

BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)
BuildArch: noarch
BuildRequires: gettext

%description
namedmanager is a web-based interface for viewing and managing DNS zones stored inside a database and generating configuration files from that.


%package www
Summary: namedmanager web-based interface and API components
Group: Applications/Internet

Requires: httpd, mod_ssl
Requires: php >= 5.3.0, mysql-server, php-mysql, php-ldap, php-soap
Requires: perl, perl-DBD-MySQL
Prereq: httpd, php, mysql-server, php-mysql

%description www
Provides the namedmanager web-based interface and SOAP API.


%package bind
Summary:  Integration components for Bind nameservers.
Group: Applications/Internet

Requires: php-cli >= 5.3.0, php-soap, php-process
Requires: perl, perl-DBD-MySQL
Requires: bind

%description bind
Provides applications for integrating with Bind nameservers and generating text-based configuration files from the API.


%prep
%setup -q -n namedmanager-%{version}

%build


%install
rm -rf $RPM_BUILD_ROOT
mkdir -p -m0755 $RPM_BUILD_ROOT%{_sysconfdir}/namedmanager/
mkdir -p -m0755 $RPM_BUILD_ROOT%{_datadir}/namedmanager/

# install application files and resources
cp -pr * $RPM_BUILD_ROOT%{_datadir}/namedmanager/


# install configuration file
install -m0700 htdocs/include/sample-config.php $RPM_BUILD_ROOT%{_sysconfdir}/namedmanager/config.php
ln -s %{_sysconfdir}/namedmanager/config.php $RPM_BUILD_ROOT%{_datadir}/namedmanager/htdocs/include/config-settings.php

# install linking config file
install -m755 htdocs/include/config.php $RPM_BUILD_ROOT%{_datadir}/namedmanager/htdocs/include/config.php


# install configuration file
install -m0700 bind/include/sample-config.php $RPM_BUILD_ROOT%{_sysconfdir}/namedmanager/config-bind.php
ln -s %{_sysconfdir}/namedmanager/config-bind.php $RPM_BUILD_ROOT%{_datadir}/namedmanager/bind/include/config-settings.php

# install linking config file
install -m755 bind/include/config.php $RPM_BUILD_ROOT%{_datadir}/namedmanager/bind/include/config.php



# install the apache configuration file
mkdir -p $RPM_BUILD_ROOT%{_sysconfdir}/httpd/conf.d
install -m 644 resources/namedmanager-httpdconfig.conf $RPM_BUILD_ROOT%{_sysconfdir}/httpd/conf.d/namedmanager.conf

# install the logpush bootscript
mkdir -p $RPM_BUILD_ROOT/etc/init.d/
install -m 755 resources/namedmanager_logpush.rcsysinit $RPM_BUILD_ROOT/etc/init.d/namedmanager_logpush

# install the cronfile
mkdir -p $RPM_BUILD_ROOT%{_sysconfdir}/cron.d/
install -m 644 resources/namedmanager-bind.cron $RPM_BUILD_ROOT%{_sysconfdir}/cron.d/namedmanager-bind

# placeholder configuration file
touch $RPM_BUILD_ROOT%{_sysconfdir}/named.namedmanager.conf

%post www

# Reload apache
echo "Reloading httpd..."
/etc/init.d/httpd reload

# update/install the MySQL DB
if [ $1 == 1 ];
then
	# install - requires manual user MySQL setup
	echo "Run cd %{_datadir}/namedmanager/resources/; ./autoinstall.pl to install the SQL database."
else
	# upgrade - we can do it all automatically! :-)
	echo "Automatically upgrading the MySQL database..."
	%{_datadir}/namedmanager/resources/schema_update.pl --schema=%{_datadir}/namedmanager/sql/ -v
fi



%post bind

if [ $1 == 0 ];
then
	# upgrading existing rpm
	echo "Restarting logging process..."
	/etc/init.d/namedmanager_logpush restart
fi


if [ $1 == 1 ];
then
	# instract about named
	echo ""
	echo "BIND/NAMED CONFIGURATION"
	echo ""
	echo "NamedManager BIND components have been installed, you will need to install"
	echo "and configure bind/named to use the configuration file by adding the"
	echo "following to /etc/named.conf:"
	echo ""
	echo "#"
	echo "# Include NamedManager Configuration"
	echo "#"
	echo ""
	echo "include \"/etc/named.namedmanager.conf\";"
	echo ""

	# instruct about config file
	echo ""
	echo "NAMEDMANAGER BIND CONFIGURATION"
	echo ""
	echo "You need to set the application configuration in %{_sysconfdir}/namedmanager/config-bind.php"
	echo ""
fi


%postun www

# check if this is being removed for good, or just so that an
# upgrade can install.
if [ $1 == 0 ];
then
	# user needs to remove DB
	echo "NamedManager has been removed, but the MySQL database and user will need to be removed manually."
fi


%preun bind

# stop running process
/etc/init.d/namedmanager_logpush stop



%clean
rm -rf $RPM_BUILD_ROOT

%files www
%defattr(-,root,root)
%config %dir %{_sysconfdir}/namedmanager
%attr(770,root,apache) %config(noreplace) %{_sysconfdir}/namedmanager/config.php
%attr(660,root,apache) %config(noreplace) %{_sysconfdir}/httpd/conf.d/namedmanager.conf
%{_datadir}/namedmanager/htdocs
%{_datadir}/namedmanager/resources
%{_datadir}/namedmanager/sql

%doc %{_datadir}/namedmanager/README
%doc %{_datadir}/namedmanager/docs/AUTHORS
%doc %{_datadir}/namedmanager/docs/CONTRIBUTORS
%doc %{_datadir}/namedmanager/docs/COPYING


%files bind
%defattr(-,root,root)
%config %dir %{_sysconfdir}/namedmanager
%config %dir %{_sysconfdir}/cron.d/namedmanager-bind
%config(noreplace) %{_sysconfdir}/named.namedmanager.conf
%config(noreplace) %{_sysconfdir}/namedmanager/config-bind.php
%{_datadir}/namedmanager/bind
/etc/init.d/namedmanager_logpush


%changelog
* Thu Apr  7 2011 Jethro Carr <jethro.carr@amberdms.com> 1.0.0_beta_2
- Released version 1.0.0_beta_2 bug fix release
* Wed Apr  6 2011 Jethro Carr <jethro.carr@amberdms.com> 1.0.0_beta_1
- Released version 1.0.0_beta_1
* Mon Mar 28 2011 Jethro Carr <jethro.carr@amberdms.com> 1.0.0_alpha_5
- Released version 1.0.0_alpha_5
* Tue Jun 08 2010 Jethro Carr <jethro.carr@amberdms.com> 1.0.0_alpha_4
- Released version 1.0.0_alpha_4
* Sun May 30 2010 Jethro Carr <jethro.carr@amberdms.com> 1.0.0_alpha_3
- Released version 1.0.0_alpha_3
* Fri May 28 2010 Jethro Carr <jethro.carr@amberdms.com> 1.0.0_alpha_2
- Released version 1.0.0_alpha_2
* Mon May 24 2010 Jethro Carr <jethro.carr@amberdms.com> 1.0.0_alpha_1
- Inital Application Release

