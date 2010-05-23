Summary: LDAPAuthManager Filter Control Application
Name: ldapauthmanager
Version: 1.0.2
Release: 1.%{?dist}
License: AGPLv3
URL: http://www.amberdms.com/ldapauthmanager
Group: Applications/Internet
Source0: ldapauthmanager-%{version}.tar.bz2

BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)
BuildArch: noarch
BuildRequires: gettext
Requires: httpd, mod_ssl
Requires: php >= 5.1.6, mysql-server, php-mysql, php-ldap
Requires: perl, perl-DBD-MySQL
Prereq: httpd, php, mysql-server, php-mysql

%description
LDAPAuthManager is an open-source PHP application providing an easy-to-use interface for managing users and groups in an LDAP authentication database.

%prep
%setup -q -n ldapauthmanager-%{version}

%build


%install
rm -rf $RPM_BUILD_ROOT
mkdir -p -m0755 $RPM_BUILD_ROOT%{_sysconfdir}/ldapauthmanager/
mkdir -p -m0755 $RPM_BUILD_ROOT%{_datadir}/ldapauthmanager/

# install application files and resources
cp -pr * $RPM_BUILD_ROOT%{_datadir}/ldapauthmanager/

# install configuration file
install -m0700 htdocs/include/sample-config.php $RPM_BUILD_ROOT%{_sysconfdir}/ldapauthmanager/config.php
ln -s %{_sysconfdir}/ldapauthmanager/config.php $RPM_BUILD_ROOT%{_datadir}/ldapauthmanager/htdocs/include/config-settings.php

# install linking config file
install -m755 htdocs/include/config.php $RPM_BUILD_ROOT%{_datadir}/ldapauthmanager/htdocs/include/config.php

# install the apache configuration file
mkdir -p $RPM_BUILD_ROOT%{_sysconfdir}/httpd/conf.d
install -m 644 resources/ldapauthmanager-httpdconfig.conf $RPM_BUILD_ROOT%{_sysconfdir}/httpd/conf.d/ldapauthmanager.conf


%post

# Reload apache
echo "Reloading httpd..."
/etc/init.d/httpd reload

# update/install the MySQL DB
if [ $1 == 1 ];
then
	# install - requires manual user MySQL setup
	echo "Run cd %{_datadir}/ldapauthmanager/resources/; ./autoinstall.pl to install the SQL database."
else
	# upgrade - we can do it all automatically! :-)
	echo "Automatically upgrading the MySQL database..."
	%{_datadir}/ldapauthmanager/resources/schema_update.pl --schema=%{_datadir}/ldapauthmanager/sql/ -v
fi


%postun

# check if this is being removed for good, or just so that an
# upgrade can install.
if [ $1 == 0 ];
then
	# user needs to remove DB
	echo "LDAPAuthManager has been removed, but the MySQL database and user will need to be removed manually."
fi


%clean
rm -rf $RPM_BUILD_ROOT

%files
%defattr(-,root,root)
%config %dir %{_sysconfdir}/ldapauthmanager
%attr(770,root,apache) %config(noreplace) %{_sysconfdir}/ldapauthmanager/config.php
%attr(660,root,apache) %config(noreplace) %{_sysconfdir}/httpd/conf.d/ldapauthmanager.conf
%{_datadir}/ldapauthmanager

%changelog
* Tue Apr 13 2010 Jethro Carr <jethro.carr@amberdms.com> 1.0.2
- Upgrade to inetOrgPerson
* Wed Mar 24 2010 Jethro Carr <jethro.carr@amberdms.com> 1.0.1
- Minor bug fixes, new features and 1.0.1 release
* Fri Mar 12 2010 Jethro Carr <jethro.carr@amberdms.com> 1.0.0
- Minor changes and 1.0.0 release.
* Wed Mar 10 2010 Jethro Carr <jethro.carr@amberdms.com> 1.0.0_beta_3
- Upgrade to include radius attribute configuration support on groups.
* Fri Feb 19 2010 Jethro Carr <jethro.carr@amberdms.com> 1.0.0_beta_2
- Upgrade to include radius attribute configuration support as optional feature.
* Wed Feb 03 2010 Jethro Carr <jethro.carr@amberdms.com> 1.0.0_beta_1
- Beta of first 1.0.0 release
* Mon Jan 25 2010 Jethro Carr <jethro.carr@amberdms.com> 1.0.0_alpha_1
- Inital Application release

