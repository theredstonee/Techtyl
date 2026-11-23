import React from 'react';

// Die Kontoseiten stehen bereits in der Hauptnavigation. Als Reiterleiste erscheinen sie nur,
// wenn die Navigation oben liegt (das regelt das Stylesheet). Die Einhaengestellen von Blueprint
// kommen unveraendert als children herein.
export default ({ className, children }: { id?: string; className?: string; children?: React.ReactNode }) => (
    <div id={'SubNavigation'} className={'techtyl-subnav techtyl-subnav--account ' + (className || '')}>
        {children}
    </div>
);
