//
//  DetailViewController.h
//  bdMobileReader
//
//  Created by Son Dao Hoang on 5/4/12.
//  Copyright (c) 2012 UET. All rights reserved.
//

#import <UIKit/UIKit.h>

@interface DetailViewController : UIViewController <UISplitViewControllerDelegate>

@property (strong, nonatomic) id detailItem;

@property (strong, nonatomic) IBOutlet UILabel *detailDescriptionLabel;

@end
